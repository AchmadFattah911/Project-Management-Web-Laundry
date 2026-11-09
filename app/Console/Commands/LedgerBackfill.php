<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mengisi ledger untuk pembayaran lunas yang belum tercatat (data sebelum ledger ada). Idempoten:
 * dijalankan berulang kali tidak menggandakan entri (UNIQUE sumber di financial_entries).
 */
class LedgerBackfill extends Command
{
    protected $signature = 'ledger:backfill {--dry-run : Hanya hitung, tanpa menulis}';

    protected $description = 'Catat penjualan (dan estimasi biaya gateway) dari pembayaran lunas lama ke ledger';

    public function handle(LedgerService $ledger): int
    {
        $missing = Payment::where('status', Payment::STATUS_PAID)
            ->whereNotExists(fn ($q) => $q->from('financial_entries')
                ->whereColumn('financial_entries.source_id', 'payments.id')
                ->where('financial_entries.source_type', 'payment')
                ->where('financial_entries.entry_type', 'sale'));

        $total = (clone $missing)->count();
        $this->info("Pembayaran lunas tanpa entri ledger: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $created = 0;
        $failed = 0;

        $missing->lazyById(200)->each(function (Payment $payment) use ($ledger, &$created, &$failed) {
            try {
                DB::transaction(fn () => $ledger->recordSale($payment));
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("Payment #{$payment->id}: {$e->getMessage()}");
            }
        });

        $this->info("Dicatat: {$created}, gagal: {$failed}. Jalankan `php artisan ledger:verify` untuk memeriksa.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
