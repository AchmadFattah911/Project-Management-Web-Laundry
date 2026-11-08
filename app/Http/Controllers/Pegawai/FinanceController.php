<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Services\FinanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        protected FinanceReportService $reports
    ) {}

    /**
     * Audit keuangan harian, mingguan, dan bulanan (hanya baca).
     */
    public function index(Request $request)
    {
        return view('pegawai.keuangan.index', ['report' => $this->report($request)]);
    }

    /**
     * Rekap transaksi periode terpilih sebagai CSV (pemisah titik koma agar langsung terbaca Excel Indonesia).
     */
    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);
        $filename = sprintf('rekap-keuangan-%s-%s-%s.csv', $report['channel'], $report['period'], $report['start']->format('Y-m-d'));

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Waktu', 'Kode', 'Pelanggan', 'Layanan', 'Saluran', 'Metode', 'Referensi', 'Petugas', 'Uang Diterima', 'Kembalian', 'Nominal', 'Selisih'], ';');

            foreach ($report['transactions'] as $row) {
                fputcsv($out, [
                    $row['time']->format('Y-m-d H:i'),
                    $row['code'],
                    $this->safeCell($row['customer']),
                    $this->safeCell(implode(', ', array_column($row['services'], 'name'))),
                    $row['channel'],
                    $row['method'],
                    $row['reference'],
                    $this->safeCell((string) $row['cashier']),
                    $row['received'],
                    $row['change'],
                    $row['amount'],
                    $row['difference'],
                ], ';');
            }

            fputcsv($out, ['Total', '', '', '', '', '', '', '', '', '', $report['total'], ''], ';');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function report(Request $request): array
    {
        $validated = $request->validate([
            'periode' => ['nullable', Rule::in(array_keys(FinanceReportService::PERIODS))],
            'kanal'   => ['nullable', Rule::in(array_keys(FinanceReportService::CHANNELS))],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = FinanceReportService::timezone();
        $date = isset($validated['tanggal'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['tanggal'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        return $this->reports->build($validated['periode'] ?? 'harian', $date, $validated['kanal'] ?? 'online');
    }

    /**
     * Nama pelanggan diisi bebas: cegah terbaca sebagai rumus saat CSV dibuka di Excel.
     */
    protected function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
