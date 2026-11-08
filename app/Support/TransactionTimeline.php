<?php

namespace App\Support;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Timeline untuk pelanggan dari order_status_logs (transaksi + pesanan di dalamnya), urut kronologis.
 *
 * Data lama (sebelum order_status_logs ada) tidak punya baris "dibuat": created_at transaksi/pesanan dipakai
 * sebagai baris "Pesanan dibuat"; transaksi lunas tanpa log lunas memakai paid_at.
 * Catatan internal (nomor referensi gateway, alasan koreksi admin) tidak ditampilkan ke pelanggan.
 */
final class TransactionTimeline
{
    /**
     * @return Collection<int, array{at: Carbon, label: string, tone: string}>
     */
    public static function for(Checkout|Order $subject): Collection
    {
        return $subject instanceof Checkout ? self::forCheckout($subject) : self::forOrder($subject);
    }

    /**
     * @return Collection<int, array{at: Carbon, label: string, tone: string}>
     */
    protected static function forCheckout(Checkout $checkout): Collection
    {
        $checkout->loadMissing(['statusLogs', 'orders.statusLogs', 'orders.service']);

        $rows = $checkout->statusLogs->map(fn (OrderStatusLog $log) => self::row($log->created_at, self::checkoutLabel($log), self::tone($log->to_status), $log->id));

        if (! $checkout->statusLogs->contains(fn ($log) => $log->from_status === null)) {
            $rows->prepend(self::row($checkout->created_at, 'Pesanan dibuat', 'info', 0));
        }

        if ($checkout->isPaid() && $checkout->paid_at && ! $checkout->statusLogs->contains('to_status', Checkout::STATUS_PAID)) {
            $rows->push(self::row($checkout->paid_at, 'Pembayaran diterima', 'success', 0));
        }

        foreach ($checkout->orders as $order) {
            // Baris "dibuat" per pesanan sudah terwakili "Pesanan dibuat" milik transaksi
            foreach ($order->statusLogs->whereNotNull('from_status') as $log) {
                $rows->push(self::row($log->created_at, self::orderLabel($log, $order), self::tone($log->to_status), $log->id));
            }
        }

        return self::sorted($rows);
    }

    /**
     * @return Collection<int, array{at: Carbon, label: string, tone: string}>
     */
    protected static function forOrder(Order $order): Collection
    {
        $order->loadMissing(['statusLogs', 'service']);

        $rows = $order->statusLogs->map(fn (OrderStatusLog $log) => self::row(
            $log->created_at,
            $log->from_status === null ? 'Pesanan dibuat' : self::orderLabel($log, $order),
            $log->from_status === null ? 'info' : self::tone($log->to_status),
            $log->id,
        ));

        if (! $order->statusLogs->contains(fn ($log) => $log->from_status === null)) {
            $rows->prepend(self::row($order->created_at, 'Pesanan dibuat', 'info', 0));
        }

        return self::sorted($rows);
    }

    protected static function checkoutLabel(OrderStatusLog $log): string
    {
        if ($log->from_status === null) {
            return 'Pesanan dibuat';
        }

        return match ($log->to_status) {
            Checkout::STATUS_PAID      => 'Pembayaran diterima',
            Checkout::STATUS_EXPIRED   => 'Batas waktu pembayaran habis',
            Checkout::STATUS_FAILED    => 'Pembayaran gagal',
            Checkout::STATUS_CANCELLED => 'Pesanan dibatalkan',
            default                    => 'Status transaksi: ' . $log->to_status,
        };
    }

    protected static function orderLabel(OrderStatusLog $log, Order $order): string
    {
        $name = $order->serviceName();
        $isBackward = $log->from_status !== null
            && array_search($log->to_status, Order::STATUSES, true) < array_search($log->from_status, Order::STATUSES, true);

        if ($isBackward) {
            return "Status {$name} dikoreksi menjadi " . (Order::STATUS_LABELS[$log->to_status] ?? $log->to_status);
        }

        return match ($log->to_status) {
            Order::STATUS_DIPROSES     => "{$name} sedang diproses",
            Order::STATUS_SIAP_DIAMBIL => "{$name} siap diambil",
            Order::STATUS_SELESAI      => "{$name} selesai",
            default                    => "{$name}: " . (Order::STATUS_LABELS[$log->to_status] ?? $log->to_status),
        };
    }

    protected static function tone(string $status): string
    {
        return match ($status) {
            Checkout::STATUS_PAID, Order::STATUS_SELESAI, Order::STATUS_SIAP_DIAMBIL => 'success',
            Checkout::STATUS_EXPIRED, Checkout::STATUS_FAILED, Checkout::STATUS_CANCELLED => 'danger',
            default => 'info',
        };
    }

    /**
     * @return array{at: Carbon, label: string, tone: string, sort: int}
     */
    protected static function row(mixed $at, string $label, string $tone, int $sort): array
    {
        return ['at' => Carbon::parse($at), 'label' => $label, 'tone' => $tone, 'sort' => $sort];
    }

    protected static function sorted(Collection $rows): Collection
    {
        return $rows->sortBy([fn ($a, $b) => $a['at'] <=> $b['at'], fn ($a, $b) => $a['sort'] <=> $b['sort']])->values();
    }
}
