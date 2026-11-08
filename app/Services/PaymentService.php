<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\IdempotencyException;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Payments\InvalidPaymentNotification;
use App\Payments\PaymentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya tempat logika pembayaran (online via gateway maupun manual di kasir).
 *
 * Aturan yang dijaga di sini:
 * - Nominal selalu diambil dari total di server (checkout / order), tidak pernah dari input user.
 * - Satu transaksi (checkout) atau satu order hanya boleh punya satu pembayaran berstatus paid.
 * - Idempotent di semua jalur:
 *     • token pembayaran dihitung server (lihat paymentToken) → token sama = tagihan sama (lihat IdempotencyService),
 *       token juga diteruskan ke gateway agar gateway tidak menagih dua kali;
 *     • notifikasi gateway dicatat di payment_events → event sama hanya diterapkan sekali;
 *     • konfirmasi kasir ganda mengembalikan pembayaran lunas yang sudah ada.
 * - Pembayaran yang sudah paid tidak pernah turun status lagi.
 * - Uang pelanggan tidak boleh "hilang": notifikasi paid yang valid untuk tagihan lama tetap dicatat;
 *   jika transaksinya sudah lunas lewat tagihan lain, pembayaran itu ditandai needs_refund.
 *
 * Urutan penguncian baris selalu: pemilik (checkout/order) → payment, untuk mencegah deadlock.
 */
class PaymentService
{
    public const OFFLINE_METHODS = ['cash', 'transfer', 'e-wallet'];

    public function __construct(
        protected IdempotencyService $idempotency
    ) {}

    /**
     * Metode pembayaran online yang boleh dipilih pelanggan (key dari config/laundry.php).
     *
     * @return list<string>
     */
    public static function onlineMethods(): array
    {
        return array_keys(config('laundry.online_payment_methods', []));
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= config('payment.default');
        $config = config("payment.gateways.{$name}");

        if (! $config || empty($config['class'])) {
            throw new InvalidArgumentException("Payment gateway '{$name}' tidak terdaftar.");
        }

        if ($name === 'dummy' && ! self::dummyEnabled()) {
            throw new RuntimeException('Dummy payment gateway tidak aktif di environment ini.');
        }

        return app($config['class']);
    }

    public static function dummyEnabled(): bool
    {
        return (bool) config('payment.gateways.dummy.enabled') && ! app()->isProduction();
    }

    // ─────────────────────────────────────────────────────────────
    //  Pembayaran online
    // ─────────────────────────────────────────────────────────────

    /**
     * Token pembayaran: identitas 1 upaya bayar, dihitung server dari transaksi yang dibayar.
     *
     * Sengaja tidak berasal dari browser. Reload, tab ganda, atau kirim ulang saat koneksi macet selalu
     * menghasilkan token yang sama, jadi semuanya bermuara ke 1 tagihan. Token baru hanya lahir jika
     * metodenya berbeda atau tagihan sebelumnya sudah ditutup (gagal/kadaluarsa/diganti).
     */
    public function paymentToken(Checkout|Order $owner, string $method): string
    {
        $closedCharges = $owner->payments()
            ->whereNotNull('reference')
            ->where(fn ($q) => $q->where('status', '!=', Payment::STATUS_PENDING)->orWhere('expires_at', '<', now()))
            ->count();

        return hash_hmac('sha256', implode('|', [
            $owner->getTable(),
            $owner->getKey(),
            $this->amountOf($owner),
            $method,
            $closedCharges,
        ]), (string) config('app.key'));
    }

    /**
     * Pelanggan membayar transaksi keranjang atau order tunggal lama.
     * Token yang sama diproses 1x; request berikutnya menerima tagihan yang sama (replayed).
     *
     * @return array{response: array<string, mixed>, replayed: bool}
     *
     * @throws IdempotencyException
     */
    public function pay(Checkout|Order $owner, string $method, User $user): array
    {
        $this->assertOnlineMethod($method);

        if ($owner instanceof Order && $owner->checkout_id) {
            throw new InvalidArgumentException('Pesanan ini dibayar melalui halaman transaksi ' . ($owner->checkout?->code ?? '') . '.');
        }

        $token = $this->paymentToken($owner, $method);
        $isCheckout = $owner instanceof Checkout;

        return $this->idempotency->run(
            key: $token,
            scope: $isCheckout ? 'checkout-payment' : 'order-payment',
            user: $user,
            payload: [($isCheckout ? 'checkout_id' : 'order_id') => $owner->getKey(), 'payment_method' => $method],
            callback: function () use ($owner, $method, $token) {
                $payment = $this->openCharge($owner, $method, $token);

                return [
                    'payment_id'   => $payment->id,
                    'reference'    => $payment->reference,
                    'checkout_url' => $payment->checkout_url,
                    'status'       => $payment->status,
                ];
            },
        );
    }

    /**
     * Membuka tagihan untuk token ini. Aman sendiri: token yang sama selalu memakai baris payment
     * yang sama (UNIQUE payments.idempotency_key), dan hanya boleh ada 1 tagihan aktif per transaksi.
     */
    protected function openCharge(Checkout|Order $owner, string $method, string $token): Payment
    {
        $gateway = $this->gateway();

        $payment = DB::transaction(function () use ($owner, $method, $token, $gateway) {
            $locked = $owner::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                throw new InvalidArgumentException(($locked instanceof Checkout ? 'Transaksi' : 'Pesanan') . ' ini sudah lunas.');
            }

            $this->assertNoChargeInFlight($locked, $token);

            $existing = Payment::where('idempotency_key', $token)->first();

            // Tagihan untuk token ini sudah terbentuk di gateway: jangan buat lagi
            if ($existing?->reference) {
                return $existing;
            }

            $this->expirePending($locked->payments(), exceptId: $existing?->id);

            $attributes = [
                'amount'  => $this->amountOf($locked),
                'method'  => $method,
                'gateway' => $gateway->name(),
                'status'  => Payment::STATUS_PENDING,
            ];

            // Percobaan sebelumnya putus sebelum gateway merespons: ulangi dengan token yang sama
            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return $locked->payments()->create($attributes + ['idempotency_key' => $token]);
        });

        if ($payment->reference) {
            return $payment;
        }

        return $this->createCharge($payment, $gateway, $token);
    }

    /**
     * Tagihan lain (metode berbeda) yang masih menunggu respons gateway tidak boleh disalip,
     * supaya 2 tagihan tidak terbentuk bersamaan di gateway untuk transaksi yang sama.
     *
     * @throws IdempotencyException
     */
    protected function assertNoChargeInFlight(Checkout|Order $owner, string $token): void
    {
        $inFlight = $owner->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('reference')
            ->where('idempotency_key', '!=', $token)
            ->where('updated_at', '>', now()->subSeconds((int) config('payment.idempotency.lock_seconds', 60)))
            ->exists();

        if ($inFlight) {
            throw IdempotencyException::inProgress();
        }
    }

    /**
     * Panggilan ke gateway dilakukan di luar transaksi DB (gateway asli = HTTP request).
     */
    protected function createCharge(Payment $payment, PaymentGateway $gateway, string $idempotencyKey): Payment
    {
        try {
            $charge = $gateway->createCharge($payment, $idempotencyKey);
        } catch (Throwable $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'meta'   => array_merge($payment->meta ?? [], ['error' => 'Gagal membuat tagihan: ' . $e->getMessage()]),
            ]);

            Log::error('Gagal membuat tagihan pembayaran', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            throw new RuntimeException('Tagihan pembayaran gagal dibuat. Silakan coba lagi beberapa saat lagi.', 0, $e);
        }

        $payment->update([
            'reference'    => $charge->reference,
            'checkout_url' => $charge->checkoutUrl,
            'expires_at'   => $charge->expiresAt,
            'meta'         => $charge->meta ?: null,
        ]);

        return $payment->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    //  Notifikasi gateway (webhook)
    // ─────────────────────────────────────────────────────────────

    /**
     * Memproses notifikasi status dari gateway (webhook). Aman dipanggil berkali-kali:
     * event yang sama hanya diterapkan sekali (payment_events).
     *
     * @param  array<string, string>  $headers  header dengan key huruf kecil
     *
     * @throws InvalidPaymentNotification
     */
    public function handleNotification(string $gatewayName, string $rawBody, array $headers): Payment
    {
        $gateway = $this->gateway($gatewayName);
        $notification = $gateway->parseNotification($rawBody, $headers);
        $eventId = $notification->eventId ?: hash('sha256', $rawBody);

        return DB::transaction(function () use ($gateway, $notification, $eventId) {
            $found = Payment::where('gateway', $gateway->name())
                ->where('reference', $notification->reference)
                ->first();

            if (! $found) {
                throw new InvalidPaymentNotification("Pembayaran dengan reference '{$notification->reference}' tidak ditemukan.");
            }

            $this->lockOwner($found);
            $payment = Payment::whereKey($found->id)->lockForUpdate()->firstOrFail();

            $alreadyProcessed = PaymentEvent::where('gateway', $gateway->name())
                ->where('event_id', $eventId)
                ->exists();

            if ($alreadyProcessed) {
                return $payment;
            }

            $this->applyNotification($payment, $notification);

            PaymentEvent::create([
                'gateway'    => $gateway->name(),
                'event_id'   => $eventId,
                'payment_id' => $payment->id,
                'status'     => $notification->status,
                'payload'    => $notification->raw,
            ]);

            return $payment;
        });
    }

    protected function applyNotification(Payment $payment, PaymentNotification $notification): Payment
    {
        // Paid tidak pernah turun status
        if ($payment->isPaid()) {
            return $payment;
        }

        $meta = array_merge($payment->meta ?? [], ['notification' => $notification->raw]);

        if ($notification->status === Payment::STATUS_PAID) {
            if ($notification->amountInCents !== $payment->amountInCents()) {
                throw new InvalidPaymentNotification('Nominal pembayaran tidak sesuai dengan tagihan.');
            }

            // Uang sudah masuk lewat tagihan ini, tetapi transaksinya sudah lunas lewat tagihan lain
            if ($this->ownerPayments($payment)->where('status', Payment::STATUS_PAID)->exists()) {
                $payment->update(['meta' => array_merge($meta, ['needs_refund' => true])]);

                Log::warning('Pembayaran ganda untuk transaksi yang sudah lunas, perlu refund manual', [
                    'payment_id'  => $payment->id,
                    'checkout_id' => $payment->checkout_id,
                    'order_id'    => $payment->order_id,
                ]);

                return $payment;
            }

            // Diterima walau tagihan sudah diganti/kadaluarsa: dana pelanggan sudah masuk, jangan diabaikan
            $payment->update([
                'status'  => Payment::STATUS_PAID,
                'paid_at' => now(),
                'meta'    => $meta,
            ]);

            $this->markOwnerPaid($payment);
        } elseif (in_array($notification->status, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)
            && $payment->status === Payment::STATUS_PENDING) {
            $payment->update([
                'status' => $notification->status,
                'meta'   => $meta,
            ]);
        }

        return $payment;
    }

    // ─────────────────────────────────────────────────────────────
    //  Pembayaran manual (kasir)
    // ─────────────────────────────────────────────────────────────

    /**
     * Admin melunasi tagihan saat notifikasi gateway tidak pernah sampai.
     * Pegawai tidak punya jalur untuk mengubah status pembayaran pesanan yang sudah ada.
     * Order dari keranjang dilunasi per transaksi (semua order di checkout yang sama ikut lunas).
     * Jika sudah lunas, pembayaran yang ada dikembalikan tanpa diubah.
     */
    public function confirmManually(Order $order, string $method, User $actor): Payment
    {
        if (! in_array($method, self::OFFLINE_METHODS, true)) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        return DB::transaction(function () use ($order, $method, $actor) {
            if ($order->checkout_id) {
                $owner = Checkout::whereKey($order->checkout_id)->lockForUpdate()->firstOrFail();
                $amount = $owner->total_amount;
            } else {
                $owner = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $amount = $owner->total_price;
            }

            $payments = $owner->payments();

            $paid = (clone $payments)->where('status', Payment::STATUS_PAID)->first();
            if ($paid) {
                return $paid;
            }

            $pending = (clone $payments)->where('status', Payment::STATUS_PENDING)->latest('id')->first();

            $attributes = [
                'amount'  => $amount,
                'method'  => $method,
                'status'  => Payment::STATUS_PAID,
                'paid_at' => now(),
            ];

            if ($pending) {
                $pending->update($attributes + [
                    'meta' => array_merge($pending->meta ?? [], ['confirmed_by' => $actor->id]),
                ]);
                $payment = $pending;
            } else {
                $payment = $owner->payments()->create($attributes + [
                    'gateway' => Payment::GATEWAY_MANUAL,
                    'meta'    => ['confirmed_by' => $actor->id],
                ]);
            }

            $this->markOwnerPaid($payment);

            return $payment;
        });
    }

    /**
     * Pembayaran pesanan kasir, dicatat lengkap untuk audit: tunai menyimpan uang diterima dan kembalian,
     * non-tunai menyimpan nomor bukti. UNIQUE payments.reference menolak 1 bukti dipakai 2 kali dan
     * UNIQUE payments.idempotency_key menolak 1 form tersimpan 2 kali, juga saat request paralel.
     */
    public function recordAtCashier(Order $order, string $method, User $actor, string $token, ?int $cashReceived, ?string $reference): Payment
    {
        if (! in_array($method, self::OFFLINE_METHODS, true)) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        $isCash = $method === 'cash';
        $total = (float) $order->total_price;

        if ($isCash && (float) $cashReceived < $total) {
            throw new InvalidArgumentException('Uang diterima kurang dari total tagihan Rp ' . number_format($total, 0, ',', '.') . '.');
        }

        if (! $isCash && ! $reference) {
            throw new InvalidArgumentException('Nomor bukti transaksi wajib diisi untuk pembayaran non-tunai.');
        }

        try {
            return $order->payments()->create([
                'amount'          => $total,
                'method'          => $method,
                'gateway'         => Payment::GATEWAY_MANUAL,
                'reference'       => $isCash ? null : $reference,
                'idempotency_key' => 'kasir:' . $token,
                'status'          => Payment::STATUS_PAID,
                'paid_at'         => now(),
                'meta'            => array_filter([
                    'confirmed_by'  => $actor->id,
                    'cash_received' => $isCash ? $cashReceived : null,
                    'change'        => $isCash ? $cashReceived - $total : null,
                ], fn ($value) => $value !== null),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Nomor bukti ini sudah dipakai, atau form ini sudah tersimpan. Periksa daftar pesanan.');
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Helper
    // ─────────────────────────────────────────────────────────────

    protected function amountOf(Checkout|Order $owner): string
    {
        return (string) ($owner instanceof Checkout ? $owner->total_amount : $owner->total_price);
    }

    protected function assertOnlineMethod(string $method): void
    {
        if (! in_array($method, self::onlineMethods(), true)) {
            throw new InvalidArgumentException('Metode pembayaran online tidak valid.');
        }
    }

    /**
     * Semua tagihan milik pemilik yang sama (checkout untuk transaksi keranjang, order untuk order tunggal).
     */
    protected function ownerPayments(Payment $payment): Builder
    {
        return $payment->checkout_id
            ? Payment::where('checkout_id', $payment->checkout_id)
            : Payment::where('order_id', $payment->order_id);
    }

    protected function lockOwner(Payment $payment): void
    {
        if ($payment->checkout_id) {
            Checkout::whereKey($payment->checkout_id)->lockForUpdate()->first();
        } elseif ($payment->order_id) {
            Order::whereKey($payment->order_id)->lockForUpdate()->first();
        }
    }

    /**
     * Setelah lunas: tagihan lain yang masih terbuka ditutup (agar tidak dibayar dua kali)
     * dan status transaksi keranjang ikut menjadi lunas.
     */
    protected function markOwnerPaid(Payment $payment): void
    {
        $this->ownerPayments($payment)
            ->where('status', Payment::STATUS_PENDING)
            ->whereKeyNot($payment->id)
            ->update(['status' => Payment::STATUS_EXPIRED]);

        if ($payment->checkout_id) {
            Checkout::whereKey($payment->checkout_id)->update(['status' => Checkout::STATUS_PAID]);
        }
    }

    protected function expirePending(HasMany $payments, ?int $exceptId = null): void
    {
        $payments->where('status', Payment::STATUS_PENDING)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->update(['status' => Payment::STATUS_EXPIRED]);
    }
}
