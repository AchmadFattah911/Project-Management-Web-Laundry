<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
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
     * Update jumlah mesin dan hitung ulang total_price.
     * Hanya untuk admin/pegawai, hanya saat status 'pending' atau 'diproses'.
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

        $process->update([
            'status'     => $status,
            'updated_by' => $actor->id,
        ]);

        return $process;
    }
}
