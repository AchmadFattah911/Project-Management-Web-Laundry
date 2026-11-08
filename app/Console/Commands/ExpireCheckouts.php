<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use App\Services\SnapPaymentService;
use Illuminate\Console\Command;

/**
 * Tutup transaksi yang lewat batas bayar (+ masa tenggang) sebagai kadaluarsa, setelah rekonsiliasi.
 * Hanya berjalan jika PAYMENT_ENABLED=true (alur dummy lama tidak diubah).
 */
class ExpireCheckouts extends Command
{
    protected $signature = 'checkouts:expire
        {--grace=5 : Masa tenggang setelah batas bayar (menit)}
        {--limit=100 : Maksimal transaksi per putaran}';

    protected $description = 'Tandai transaksi yang lewat batas bayar sebagai kadaluarsa (cek Midtrans dulu)';

    public function handle(PaymentReconciliationService $service): int
    {
        if (! SnapPaymentService::enabled()) {
            $this->info('PAYMENT_ENABLED=false, transaksi tidak dikadaluarsakan.');

            return self::SUCCESS;
        }

        $stats = $service->expireOverdue(max(0, (int) $this->option('grace')), max(1, (int) $this->option('limit')));

        $this->table(['Hasil', 'Jumlah'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());

        return self::SUCCESS;
    }
}
