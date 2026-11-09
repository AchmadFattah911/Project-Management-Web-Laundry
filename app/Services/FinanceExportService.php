<?php

namespace App\Services;

use App\Data\ReportFilter;
use App\Exceptions\ExportIntegrityException;
use App\Jobs\GenerateFinanceExport;
use App\Models\FinanceExport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orkestrasi export: setiap permintaan tercatat (finance_exports + audit_logs). Rentang dengan entri ledger
 * di atas finance.export.sync_row_limit diproses lewat queue dan diunduh dari halaman "Ekspor Saya".
 */
class FinanceExportService
{
    public function __construct(
        protected FinanceExcelExporter $exporter,
        protected FinancialReportService $reports,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{export: FinanceExport, queued: bool}
     */
    public function request(ReportFilter $filter, User $actor): array
    {
        $export = FinanceExport::create([
            'user_id' => $actor->id,
            'filters' => $filter->toQuery(),
            'status'  => FinanceExport::STATUS_QUEUED,
        ]);

        $rows = $this->reports->countEntries($filter);
        $queued = $rows > (int) config('finance.export.sync_row_limit', 5000);

        $this->audit->log('laporan.export', $export, null, ['filters' => $export->filters, 'entries' => $rows, 'queued' => $queued], null, $actor);

        if ($queued) {
            GenerateFinanceExport::dispatch($export->id);
        } else {
            $this->generate($export);
        }

        return ['export' => $export->fresh(), 'queued' => $queued];
    }

    /**
     * Tulis file ke disk private. Gagal (termasuk Kontrol ≠ 0) → status failed + log + audit, tanpa file.
     */
    public function generate(FinanceExport $export): FinanceExport
    {
        $export->update(['status' => FinanceExport::STATUS_PROCESSING]);

        $disk = Storage::disk((string) config('finance.export.disk', 'local'));
        $relative = 'exports/' . now()->format('Y/m') . '/' . Str::uuid() . '.xlsx';
        $disk->makeDirectory(dirname($relative));
        $absolute = $disk->path($relative);

        try {
            $result = $this->exporter->write($export->filter(), $export->user, $absolute);
        } catch (Throwable $e) {
            @unlink($absolute);
            $export->update(['status' => FinanceExport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000), 'completed_at' => now()]);

            Log::error('finance.export_failed', [
                'export_id'   => $export->id,
                'error'       => $e->getMessage(),
                'differences' => $e instanceof ExportIntegrityException ? $e->differences : null,
            ]);
            $this->audit->log('laporan.export.failed', $export, null, ['error' => $e->getMessage()], null, $export->user);

            return $export;
        }

        $export->update([
            'status'       => FinanceExport::STATUS_DONE,
            'path'         => $relative,
            'rows'         => $result['rows'],
            'completed_at' => now(),
        ]);

        return $export;
    }

    public function paginateFor(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return FinanceExport::where('user_id', $user->id)->latest('id')->paginate($perPage);
    }
}
