<?php

namespace App\Console\Commands;

use App\Services\LedgerVerifier;
use Illuminate\Console\Command;

/**
 * Membandingkan ledger dengan sumbernya (payments, void, refund, pengeluaran). Selisih ditampilkan dan
 * ditandai perlu_tinjau di ledger_reviews (ledger tidak pernah diubah). Exit code 1 jika ada selisih.
 */
class LedgerVerify extends Command
{
    protected $signature = 'ledger:verify {--no-mark : Jangan simpan temuan ke ledger_reviews}';

    protected $description = 'Periksa kecocokan ledger keuangan dengan data sumbernya';

    public function handle(LedgerVerifier $verifier): int
    {
        $issues = $verifier->verify();

        if ($issues === []) {
            $this->info('Ledger cocok dengan sumbernya: 0 selisih.');

            return self::SUCCESS;
        }

        $this->table(
            ['Sumber', 'ID', 'Masalah', 'Detail'],
            array_map(fn ($i) => [$i['source_type'], $i['source_id'], $i['issue'], json_encode($i['detail'])], $issues),
        );

        if (! $this->option('no-mark')) {
            $verifier->markForReview($issues);
            $this->warn(count($issues) . ' selisih ditandai perlu_tinjau di ledger_reviews.');
        }

        return self::FAILURE;
    }
}
