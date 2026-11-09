<?php

namespace App\Http\Controllers\Panel;

use App\Data\ReportFilter;
use App\Http\Controllers\Controller;
use App\Models\FinanceExport;
use App\Services\FinanceExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Export Excel laporan keuangan (can:laporan.export) dan halaman "Ekspor Saya".
 */
class ExportController extends Controller
{
    public function __construct(protected FinanceExportService $exports) {}

    public function index(Request $request)
    {
        return view('panel.ekspor.index', ['exports' => $this->exports->paginateFor($request->user())]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kanal'   => ['nullable', 'in:' . implode(',', array_keys(ReportFilter::CHANNELS))],
            'periode' => ['nullable', 'in:harian,mingguan,bulanan'],
            'dari'    => ['nullable', 'date_format:Y-m-d'],
            'sampai'  => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $filter = ReportFilter::fromArray($request->only(['kanal', 'periode', 'dari', 'sampai']));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $result = $this->exports->request($filter, $request->user());
        $export = $result['export'];

        return match (true) {
            $result['queued'] => redirect()->route('panel.ekspor.index')->with('status', 'Rentang besar: export diproses di latar belakang. Unduh dari halaman ini setelah siap.'),
            $export->status === FinanceExport::STATUS_DONE => redirect()->route('panel.ekspor.download', $export),
            default => back()->with('error', 'Export gagal: ' . $export->error),
        };
    }

    public function download(Request $request, FinanceExport $export)
    {
        // Hanya pemilik export; lainnya 404
        abort_unless((int) $export->user_id === (int) $request->user()->id && $export->status === FinanceExport::STATUS_DONE, 404);

        $disk = Storage::disk((string) config('finance.export.disk', 'local'));
        abort_unless($export->path && $disk->exists($export->path), 404);

        return $disk->download($export->path, $export->fileName(), [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
