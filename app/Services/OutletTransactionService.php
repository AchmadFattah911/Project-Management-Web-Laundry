<?php

namespace App\Services;

use App\Data\OutletTransactionData;
use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Transaksi outlet (kasir): header checkouts channel=outlet + item orders, harga dari server.
 *
 *   bayar sekarang   → langsung lunas; tunai wajib terikat ke shift aktif petugas
 *   bayar saat ambil → menunggu_pembayaran; boleh dikerjakan, dilunasi saat diambil (ledger dicatat saat lunas)
 *
 * Edit hanya sebelum lunas. Koreksi setelah lunas = void (lihat VoidService), bukan edit/hapus.
 * Urutan lock: checkout → cash_shift.
 */
class OutletTransactionService
{
    public function __construct(
        protected IdempotencyService $idempotency,
        protected OrderService $orders,
        protected OrderStateMachine $stateMachine,
        protected LedgerService $ledger,
        protected ShiftService $shifts,
        protected CustomerService $customers,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{checkout: Checkout, replayed: bool, change: int}
     */
    public function create(OutletTransactionData $data, User $actor, string $token): array
    {
        $this->assertStaff($actor);

        $result = $this->idempotency->run(
            key: 'outlet:' . $token,
            scope: 'outlet-transaction',
            user: $actor,
            payload: $data->toArray(),
            callback: fn () => DB::transaction(function () use ($data, $actor, $token) {
                $customer = $data->customerId
                    ? $this->activeCustomer($data->customerId)
                    : $this->customers->createWalkIn($data->newCustomer, $actor);

                $shift = $data->paysNow() && $data->method === 'cash' ? $this->shifts->lockActiveFor($actor) : null;

                $checkout = Checkout::create([
                    'code'             => $this->generateCode(),
                    'channel'          => Checkout::CHANNEL_OUTLET,
                    'user_id'          => $customer->id,
                    'created_by'       => $actor->id,
                    'total_amount'     => 0,
                    'status'           => Checkout::STATUS_AWAITING,
                    'payment_timing'   => $data->paymentTiming,
                    'outlet_method'    => $data->method,
                    'shift_id'         => $shift?->id,
                    'contact_phone'    => $customer->phone ?: '-',
                    'notes'            => $data->notes,
                    'submission_token' => $token,
                ]);

                $this->stateMachine->recordCreated($checkout, $actor, 'Transaksi outlet dibuat di kasir');

                $total = 0;
                foreach ($data->items as $item) {
                    $order = $this->orders->create([
                        'checkout_id'    => $checkout->id,
                        'user_id'        => $customer->id,
                        'service_id'     => $item['service_id'],
                        'machine_qty'    => $item['qty'],
                        'item_type'      => $item['item_type'],
                        'notes'          => $item['notes'],
                        'customer_name'  => $customer->name,
                        'customer_phone' => $customer->phone,
                    ], actor: $actor);

                    $total += Rupiah::parse($order->total_price);
                }

                $checkout->update(['total_amount' => Rupiah::toDecimal($total)]);

                $payment = $data->paysNow()
                    ? $this->settleLocked($checkout, $data->method, $data->cashReceived, $data->reference, $actor, $shift, $token)
                    : null;

                $this->audit->log('transaksi.outlet.create', $checkout, null, [
                    'code'     => $checkout->code,
                    'customer' => $customer->id,
                    'total'    => $total,
                    'timing'   => $data->paymentTiming,
                    'method'   => $data->method,
                    'items'    => $data->items,
                ], null, $actor);

                return ['checkout_id' => $checkout->id, 'change' => (int) ($payment?->meta['change'] ?? 0)];
            }),
        );

        return [
            'checkout' => Checkout::findOrFail($result['response']['checkout_id']),
            'replayed' => $result['replayed'],
            'change'   => (int) ($result['response']['change'] ?? 0),
        ];
    }

    /**
     * Ubah jumlah mesin / catatan sebelum lunas. Harga satuan tetap snapshot saat transaksi dibuat.
     *
     * @param  array<int|string, int>  $quantities  order_id => qty baru
     */
    public function update(Checkout $checkout, array $quantities, ?string $notes, User $actor): Checkout
    {
        $this->assertStaff($actor);

        return DB::transaction(function () use ($checkout, $quantities, $notes, $actor) {
            $locked = $this->lockEditable($checkout);
            $orders = Order::where('checkout_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $before = ['notes' => $locked->notes, 'total' => $locked->totalRupiah(), 'qty' => $orders->map->machine_qty->all()];
            $max = (int) config('laundry.max_machine_qty', 20);
            $total = 0;

            foreach ($orders as $order) {
                $qty = (int) ($quantities[$order->id] ?? $order->machine_qty);

                if ($qty < 1 || $qty > $max) {
                    throw new InvalidArgumentException("Jumlah mesin harus antara 1 sampai {$max}.");
                }

                $line = Rupiah::parse($order->unit_price) * $qty;
                $order->update(['machine_qty' => $qty, 'total_price' => Rupiah::toDecimal($line)]);
                $total += $line;
            }

            $locked->update(['total_amount' => Rupiah::toDecimal($total), 'notes' => $notes]);

            $this->audit->log('transaksi.outlet.update', $locked, $before, [
                'notes' => $notes, 'total' => $total, 'qty' => $orders->map->machine_qty->all(),
            ], null, $actor);

            return $locked;
        });
    }

    /**
     * Pelunasan "bayar saat ambil". Ledger penjualan dicatat sekarang (saat uang diterima).
     */
    public function settle(Checkout $checkout, string $method, ?int $cashReceived, ?string $reference, User $actor, string $token): Payment
    {
        $this->assertStaff($actor);

        if ($existing = Payment::where('idempotency_key', 'outlet-pay:' . $token)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($checkout, $method, $cashReceived, $reference, $actor, $token) {
            $locked = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();

            if ($locked->isOutlet() && $locked->isPaid()) {
                throw new InvalidArgumentException("Transaksi {$locked->code} sudah lunas.");
            }

            $this->assertEditableState($locked);
            $shift = $method === 'cash' ? $this->shifts->lockActiveFor($actor) : null;

            return $this->settleLocked($locked, $method, $cashReceived, $reference, $actor, $shift, $token);
        });
    }

    protected function settleLocked(Checkout $locked, string $method, ?int $cashReceived, ?string $reference, User $actor, ?CashShift $shift, string $token): Payment
    {
        $methods = config('finance.outlet_methods', []);

        if (! isset($methods[$method])) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        $total = $locked->totalRupiah();
        $isCash = $method === 'cash';

        if ($isCash && ($cashReceived === null || $cashReceived < $total)) {
            throw new InvalidArgumentException('Uang diterima kurang dari total tagihan Rp ' . number_format($total, 0, ',', '.') . '.');
        }

        if (! $isCash && ! $reference) {
            throw new InvalidArgumentException('Nomor bukti transaksi wajib diisi untuk pembayaran ' . $methods[$method]['label'] . '.');
        }

        try {
            $payment = DB::transaction(fn () => $locked->payments()->create([
                'amount'          => Rupiah::toDecimal($total),
                'method'          => $methods[$method]['method'],
                'gateway'         => Payment::GATEWAY_MANUAL,
                'reference'       => $isCash ? null : $reference,
                'idempotency_key' => 'outlet-pay:' . $token,
                'status'          => Payment::STATUS_PAID,
                'paid_at'         => now(),
                'meta'            => array_filter([
                    'confirmed_by'  => $actor->id,
                    'outlet_method' => $method,
                    'shift_id'      => $shift?->id,
                    'cash_received' => $isCash ? $cashReceived : null,
                    'change'        => $isCash ? $cashReceived - $total : null,
                ], fn ($v) => $v !== null),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Nomor bukti ini sudah dipakai untuk pembayaran lain.');
        }

        if ($shift && ! $locked->shift_id) {
            $locked->update(['shift_id' => $shift->id]);
        }

        $this->stateMachine->transitionCheckout($locked, Checkout::STATUS_PAID, OrderStateMachine::actorTypeFor($actor), $actor,
            'Dibayar di kasir (' . $methods[$method]['label'] . ')');
        $this->ledger->recordSale($payment, $shift?->id);

        $this->audit->log('transaksi.outlet.settle', $locked, ['status' => Checkout::STATUS_AWAITING], [
            'status' => Checkout::STATUS_PAID, 'payment_id' => $payment->id, 'method' => $method, 'amount' => $total,
        ], null, $actor);

        return $payment;
    }

    protected function lockEditable(Checkout $checkout): Checkout
    {
        $locked = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();
        $this->assertEditableState($locked);

        return $locked;
    }

    protected function assertEditableState(Checkout $checkout): void
    {
        if (! $checkout->isOutlet()) {
            throw new InvalidArgumentException('Transaksi aplikasi tidak bisa diubah dari kasir.');
        }

        if ($checkout->isPaid()) {
            throw new InvalidArgumentException("Transaksi {$checkout->code} sudah lunas dan tidak bisa diubah. Ajukan void jika ada kesalahan.");
        }

        if (! $checkout->isAwaitingPayment() || $checkout->isVoided()) {
            throw new InvalidArgumentException("Transaksi {$checkout->code} sudah ditutup.");
        }
    }

    protected function activeCustomer(int $id): User
    {
        $customer = User::where('role', UserRole::PELANGGAN->value)->find($id);

        if (! $customer || ! $customer->isActive()) {
            throw new InvalidArgumentException('Pelanggan tidak ditemukan atau sudah nonaktif.');
        }

        return $customer;
    }

    protected function assertStaff(User $actor): void
    {
        if (! $actor->isAdmin() && ! $actor->isPegawai()) {
            throw new InvalidArgumentException('Hanya admin atau pegawai yang dapat mengelola transaksi outlet.');
        }
    }

    protected function generateCode(): string
    {
        do {
            $code = 'OTL-' . strtoupper(Str::random(8));
        } while (Checkout::where('code', $code)->exists());

        return $code;
    }
}
