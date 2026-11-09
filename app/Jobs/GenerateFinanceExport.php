<?php

namespace App\Jobs;

use App\Models\FinanceExport;
use App\Services\FinanceExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Export Excel rentang besar (di atas finance.export.sync_row_limit entri ledger).
 */
class GenerateFinanceExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $exportId) {}

    public function handle(FinanceExportService $exports): void
    {
        $export = FinanceExport::find($this->exportId);

        if ($export && $export->status === FinanceExport::STATUS_QUEUED) {
            $exports->generate($export);
        }
    }
}
