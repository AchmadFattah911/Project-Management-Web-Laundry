<?php

namespace App\Http\Controllers\Panel;

use App\Data\ReportFilter;
use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Laporan keuangan (hanya admin, can:laporan.view). Semua angka dari ledger.
 */
class ReportController extends Controller
{
    public function __construct(protected FinancialReportService $reports) {}

    public function index(Request $request)
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
            return redirect()->route('panel.laporan.index')->with('error', $e->getMessage());
        }

        return view('panel.laporan.index', ['report' => $this->reports->build($filter)]);
    }
}
