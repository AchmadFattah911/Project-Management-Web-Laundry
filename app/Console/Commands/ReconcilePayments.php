<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;

/**
 * Cek ulang tagihan Midtrans yang masih pending ke API status (jika webhook hilang / terlambat).
 * Dijadwalkan tiap 5 menit (routes/console.php).
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=100 : Maksimal tagihan yang dicek per putaran}';

    protected $description = 'Cocokkan tagihan Midtrans yang masih pending dengan status di Midtrans';

    public function handle(PaymentReconciliationService $service): int
    {
        if (! PaymentReconciliationService::serverKeyConfigured()) {
            $this->info('MIDTRANS_SERVER_KEY belum diisi, rekonsiliasi dilewati.');

            return self::SUCCESS;
        }

        $stats = $service->reconcilePending(max(1, (int) $this->option('limit')));

        $this->table(['Hasil', 'Jumlah'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());

        return isset($stats['error']) ? self::FAILURE : self::SUCCESS;
    }
}
