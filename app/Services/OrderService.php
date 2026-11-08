<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        protected PaymentService $payments,
        protected IdempotencyService $idempotency
    ) {}

    /**
     * Membuat pesanan baru dalam DB Transaction.
     * Menolak jika layanan berstatus bukan 'tersedia'.
     */
    public function create(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $service = Service::findOrFail($data['service_id']);

            if ($service->status !== 'tersedia') {
                throw new InvalidArgumentException("Layanan '{$service->name}' sedang tidak tersedia untuk dipesan.");
            }

            $machineQty = max(1, (int) ($data['machine_qty'] ?? 1));
            $unitPrice = (float) $service->price;
            $totalPrice = $unitPrice * $machineQty;

            // Generate order_code unik: format LC-XXXXXX
            do {
                $orderCode = 'LC-' . strtoupper(Str::random(6));
            } while (Order::where('order_code', $orderCode)->exists());

            $userId = $data['user_id'] ?? $user?->id;
            $customerName = $data['customer_name'] ?? ($user?->name ?? null);
            $customerPhone = $data['customer_phone'] ?? ($user?->phone ?? null);

            $order = Order::create([
                'order_code'     => $orderCode,
                'checkout_id'    => $data['checkout_id'] ?? null,
                'user_id'        => $userId,
                'service_id'     => $service->id,
                'customer_name'  => $customerName,
                'customer_phone' => $customerPhone,
                'machine_qty'    => $machineQty,
                'unit_price'     => $unitPrice,
                'total_price'    => $totalPrice,
                'item_type'      => $data['item_type'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'status'         => 'pending',
            ]);

            // Non-Self Service: buat order_processes otomatis sesuai tahapan layanan (lihat Service::processSteps)
            foreach ($service->processSteps() as $proc) {
                OrderProcess::create([
                    'order_id'   => $order->id,
                    'process'    => $proc,
                    'status'     => 'pending',
                    'updated_by' => null,
                ]);
            }

            return $order;
        });
    }

    /**
     * Pesanan kasir: bayar dulu, jadi pesanan dan pelunasannya tersimpan bersama atau tidak sama sekali.
     * Token form diproses 1x (lihat IdempotencyService); kirim ulang menerima pesanan yang sama.
     * Harga selalu dari layanan di server, field di luar daftar ini tidak pernah sampai ke create().
     *
     * @return array{response: array<string, mixed>, replayed: bool}
     */
    public function createWalkIn(array $data, User $actor): array
    {
        if (! $actor->isAdmin() && ! $actor->isPegawai()) {
            throw new InvalidArgumentException('Hanya admin atau pegawai yang dapat membuat pesanan kasir.');
        }

        $token = $data['submission_token'];

        return $this->idempotency->run(
            key: 'kasir:' . $token,
            scope: 'cashier-order',
            user: $actor,
            payload: Arr::except($data, 'submission_token'),
            callback: fn () => DB::transaction(function () use ($data, $actor, $token) {
                $order = $this->create($this->walkInAttributes($data));

                $payment = $this->payments->recordAtCashier(
                    $order,
                    $data['payment_method'],
                    $actor,
                    $token,
                    isset($data['cash_received']) ? (int) $data['cash_received'] : null,
                    $data['payment_reference'] ?? null,
                );

                return [
                    'payment_id' => $payment->id,
                    'order_code' => $order->order_code,
                    'change'     => $payment->meta['change'] ?? 0,
                ];
            }),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function walkInAttributes(array $data): array
    {
        $attributes = Arr::only($data, ['service_id', 'machine_qty', 'item_type', 'notes', 'customer_name', 'customer_phone', 'user_id']);

        if (empty($attributes['user_id'])) {
            return $attributes;
        }

        $customer = User::where('role', UserRole::PELANGGAN->value)->find($attributes['user_id']);

        if (! $customer) {
            throw new InvalidArgumentException('Pesanan hanya bisa dibuat untuk akun pelanggan.');
        }

        return ['customer_name' => $customer->name, 'customer_phone' => $customer->phone] + $attributes;
    }

    /**
     * Update jumlah mesin dan hitung ulang total_price.
     * Hanya untuk pesanan yang belum dibayar: setelah lunas, total harus tetap sama dengan uang yang diterima.
     */
    public function updateMachineQty(Order $order, int $newQty, User $actor): Order
    {
        $role = $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role;
        if (!in_array($role, ['admin', 'pegawai'])) {
            throw new InvalidArgumentException("Hanya admin atau pegawai yang dapat mengubah jumlah mesin.");
        }

        if (!in_array($order->status, ['pending', 'diproses'])) {
            throw new InvalidArgumentException("Jumlah mesin hanya dapat diubah saat status pesanan 'pending' atau 'diproses'.");
        }

        if ($order->checkout_id || $order->isPaid()) {
            throw new InvalidArgumentException("Jumlah mesin tidak dapat diubah karena nominal pesanan sudah ditagih atau dibayar.");
        }

        if ($newQty < 1 || $newQty > 20) {
            throw new InvalidArgumentException("Jumlah mesin harus antara 1 sampai 20 unit.");
        }

        return DB::transaction(function () use ($order, $newQty) {
            $totalPrice = (float) $order->unit_price * $newQty;

            $order->update([
                'machine_qty' => $newQty,
                'total_price' => $totalPrice,
            ]);

            return $order;
        });
    }

    /**
     * Update status pesanan (pending → diproses → siap_diambil → selesai).
     * Validasi role pegawai/admin.
     */
    public function updateStatus(Order $order, string $newStatus, User $actor): Order
    {
        $role = $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role;
        if (!in_array($role, ['admin', 'pegawai'])) {
            throw new InvalidArgumentException("Hanya admin atau pegawai yang berwenang mengubah status pesanan.");
        }

        if (!in_array($newStatus, Order::STATUSES, true)) {
            throw new InvalidArgumentException("Status '{$newStatus}' tidak valid.");
        }

        $this->assertPaid($order);

        $order->update(['status' => $newStatus]);
        return $order;
    }

    /**
     * Update status tahapan pengerjaan (cuci, kering, lipat) dan catat updated_by.
     * Validasi role pegawai/admin.
     */
    public function updateProcess(OrderProcess $process, string $status, User $actor): OrderProcess
    {
        $role = $actor->role instanceof UserRole ? $actor->role->value : (string) $actor->role;
        if (!in_array($role, ['admin', 'pegawai'])) {
            throw new InvalidArgumentException("Hanya admin atau pegawai yang dapat memperbarui tahapan pengerjaan.");
        }

        $validStatuses = ['pending', 'selesai'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Status pengerjaan '{$status}' tidak valid.");
        }

        $this->assertPaid($process->order);

        $process->update([
            'status'     => $status,
            'updated_by' => $actor->id,
        ]);

        return $process;
    }

    /**
     * Bayar dulu baru dikerjakan: pesanan yang belum dibayar tidak boleh masuk pengerjaan.
     */
    protected function assertPaid(Order $order): void
    {
        if (! $order->isPaid()) {
            throw new InvalidArgumentException("Pesanan {$order->order_code} belum dibayar sehingga belum bisa dikerjakan.");
        }
    }
}
