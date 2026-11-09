<?php

namespace App\Services;

use App\Data\ReportFilter;
use App\Exceptions\ExportIntegrityException;
use App\Models\Expense;
use App\Models\FinancialEntry;
use App\Models\User;
use App\Support\BusinessCalendar;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export Excel laporan keuangan (openspout, streaming: hemat memori untuk rentang besar).
 *
 * Sheet: Info, Ringkasan, Outlet, Online, Refund & Void, Pengeluaran, Kontrol.
 * Angka = sel NUMBER berformat Rp, tanggal = sel DATE; header dibekukan + autofilter.
 * Teks diawali = + - @ (atau tab/CR) di-escape dengan apostrof dan SELALU ditulis sebagai string
 * (Cell::fromValue mengubah "=..." menjadi formula, jadi tidak dipakai untuk teks).
 *
 * Sheet Kontrol membandingkan ringkasan (agregasi DB) dengan jumlah detail (dihitung saat menulis baris).
 * Selisih ≠ 0 → file dihapus dan ExportIntegrityException dilempar (tidak menerbitkan file cacat).
 */
class FinanceExcelExporter
{
    public const DETAIL_HEADERS = [
        'Tanggal Bisnis', 'Waktu', 'Kode Transaksi', 'Saluran', 'Pelanggan', 'Layanan', 'Metode Bayar',
        'Jenis Entri', 'Bruto', 'Biaya', 'Neto', 'Petugas',
    ];

    private const DETAIL_WIDTHS = [14, 18, 20, 10, 24, 34, 16, 22, 14, 14, 14, 18];

    private const RUPIAH_FORMAT = '"Rp" #,##0;-"Rp" #,##0';

    private Style $rupiah;

    private Style $date;

    private Style $dateTime;

    private Style $header;

    private Style $count;

    public function __construct(protected FinancialReportService $reports)
    {
        $this->rupiah = (new Style)->withFormat(self::RUPIAH_FORMAT);
        $this->date = (new Style)->withFormat('yyyy-mm-dd');
        $this->dateTime = (new Style)->withFormat('yyyy-mm-dd hh:mm');
        $this->count = (new Style)->withFormat('#,##0');
        $this->header = (new Style)->withFontBold(true)->withBackgroundColor('E0F2FE');
    }

    /**
     * @return array{rows: int, controls: array<string, array{summary: int, detail: int, difference: int}>}
     *
     * @throws ExportIntegrityException
     */
    public function write(ReportFilter $filter, User $actor, string $path): array
    {
        $kpi = $this->reports->kpi($filter);
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $this->infoSheet($writer, $filter, $actor);
            $this->summarySheet($writer, $filter, $kpi);

            $outlet = $this->detailSheet($writer, 'Outlet', $this->entries($filter)->where('channel', FinancialEntry::CHANNEL_OUTLET));
            $online = $this->detailSheet($writer, 'Online', $this->entries($filter)->where('channel', FinancialEntry::CHANNEL_ONLINE));
            $corrections = $this->detailSheet($writer, 'Refund & Void', $this->entries($filter)->whereIn('entry_type', [FinancialEntry::TYPE_REFUND, FinancialEntry::TYPE_VOID_REVERSAL]));
            $expenses = $this->detailSheet($writer, 'Pengeluaran', $this->entries($filter)->where('entry_type', FinancialEntry::TYPE_EXPENSE));

            $controls = [
                'Laba ringkasan vs Σ neto detail (Outlet + Online + Pengeluaran)' => [$kpi['profit'], $outlet['net'] + $online['net'] + $expenses['net']],
                'Neto Gabungan vs Outlet + Online'                                 => [$kpi['net'], $outlet['net'] + $online['net']],
                'Bruto ringkasan vs Σ penjualan detail'                            => [$kpi['gross'], $outlet['sales'] + $online['sales']],
                'Void + Refund ringkasan vs sheet Refund & Void'                   => [$kpi['void'] + $kpi['refund'], $corrections['net']],
                'Pengeluaran ringkasan vs sheet Pengeluaran'                       => [$kpi['expense'], $expenses['net']],
                'Jumlah entri ledger vs jumlah baris detail'                       => [$this->reports->countEntries($filter), $outlet['rows'] + $online['rows'] + $expenses['rows']],
            ];
            $controls = array_map(fn ($pair) => ['summary' => $pair[0], 'detail' => $pair[1], 'difference' => $pair[0] - $pair[1]], $controls);

            $this->controlSheet($writer, $controls);
        } finally {
            $writer->close();
        }

        $totalDifference = array_sum(array_map(fn ($c) => abs($c['difference']), $controls));

        if ($totalDifference !== 0) {
            @unlink($path);

            throw new ExportIntegrityException('Export dibatalkan: sheet Kontrol menemukan selisih Rp ' . number_format($totalDifference, 0, ',', '.') . '.',
                array_map(fn ($c) => $c['difference'], $controls));
        }

        return ['rows' => $outlet['rows'] + $online['rows'] + $expenses['rows'], 'controls' => $controls];
    }

    /**
     * Netralkan teks yang bisa dibaca sebagai formula oleh aplikasi spreadsheet.
     */
    public static function escape(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    // ─────────────────────────────────────────────────────────────

    protected function infoSheet(Writer $writer, ReportFilter $filter, User $actor): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Info');
        $sheet->setColumnWidth(26, 1);
        $sheet->setColumnWidth(48, 2);

        $tz = BusinessCalendar::timezone();
        $rows = [
            ['Laporan', $this->text('Laporan Keuangan ' . config('laundry.store_name', 'Laundry Coin'))],
            ['Periode dari', new DateTimeCell($this->dateOf($filter->from->toDateString()), $this->date)],
            ['Periode sampai', new DateTimeCell($this->dateOf($filter->to->toDateString()), $this->date)],
            ['Granularitas', $this->text(ucfirst($filter->bucket) . ($filter->bucket === 'mingguan' ? ' (Senin–Minggu)' : ''))],
            ['Saluran', $this->text(ReportFilter::CHANNELS[$filter->channel])],
            ['Zona waktu', $this->text($tz)],
            ['Dibuat oleh', $this->text($actor->name . ' (' . $actor->email . ')')],
            ['Waktu dibuat', new DateTimeCell(new DateTimeImmutable('now', new DateTimeZone($tz)), $this->dateTime)],
            ['Catatan', $this->text('Biaya gateway adalah ESTIMASI dari tarif per metode di konfigurasi, bukan tagihan aktual Midtrans.')],
            ['Sumber data', $this->text('Ledger keuangan (financial_entries), dikelompokkan per tanggal bisnis.')],
        ];

        foreach ($rows as [$label, $cell]) {
            $writer->addRow(new Row([new StringCell($label, $this->header), $cell]));
        }
    }

    /**
     * @param  array<string, int>  $kpi
     */
    protected function summarySheet(Writer $writer, ReportFilter $filter, array $kpi): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Ringkasan');
        $sheet->setColumnWidth(30, 1);
        $sheet->setColumnWidthForRange(18, 2, 6);

        $writer->addRow($this->headerRow(['Indikator', 'Nilai']));

        foreach ([
            'Pendapatan bruto'          => 'gross',
            'Void'                      => 'void',
            'Refund'                    => 'refund',
            'Biaya gateway (estimasi)'  => 'gateway_fee',
            'Pendapatan neto'           => 'net',
            'Pengeluaran'               => 'expense',
            'Laba'                      => 'profit',
            'Rata-rata nilai transaksi' => 'average',
        ] as $label => $key) {
            $writer->addRow(new Row([new StringCell($label), new NumericCell($kpi[$key], $this->rupiah)]));
        }
        $writer->addRow(new Row([new StringCell('Jumlah transaksi'), new NumericCell($kpi['transactions'], $this->count)]));

        $writer->addRow(new Row([new EmptyCell(null)]));
        $writer->addRow($this->headerRow(['Metode bayar', 'Transaksi', 'Bruto', 'Void/Refund', 'Biaya (estimasi)', 'Neto']));

        foreach ($this->reports->byMethod($filter) as $method) {
            $writer->addRow(new Row([
                $this->text($this->methodLabel($method['method'])),
                new NumericCell($method['count'], $this->count),
                new NumericCell($method['gross'], $this->rupiah),
                new NumericCell($method['corrections'], $this->rupiah),
                new NumericCell($method['fee'], $this->rupiah),
                new NumericCell($method['net'], $this->rupiah),
            ]));
        }
    }

    /**
     * @return array{rows: int, net: int, sales: int}
     */
    protected function detailSheet(Writer $writer, string $name, Builder $query): array
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($name);
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));

        foreach (self::DETAIL_WIDTHS as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $writer->addRow($this->headerRow(self::DETAIL_HEADERS));

        $totals = ['rows' => 0, 'net' => 0, 'sales' => 0];
        $tz = new DateTimeZone(BusinessCalendar::timezone());

        $query->chunkById(500, function ($entries) use ($writer, &$totals, $tz) {
            $expenses = Expense::with('category:id,name')
                ->whereIn('id', $entries->where('source_type', FinancialEntry::SOURCE_EXPENSE)->pluck('source_id'))
                ->get()
                ->keyBy('id');

            foreach ($entries as $entry) {
                $writer->addRow($this->detailRow($entry, $expenses->get($entry->source_id), $tz));
                $totals['rows']++;
                $totals['net'] += $entry->amount;
                $totals['sales'] += $entry->entry_type === FinancialEntry::TYPE_SALE ? $entry->amount : 0;
            }
        });

        $sheet->setAutoFilter(new AutoFilter(0, 1, count(self::DETAIL_HEADERS) - 1, max(2, $totals['rows'] + 1)));

        return $totals;
    }

    protected function detailRow(FinancialEntry $entry, ?Expense $expense, DateTimeZone $tz): Row
    {
        $isExpense = $entry->entry_type === FinancialEntry::TYPE_EXPENSE;
        $isFee = $entry->entry_type === FinancialEntry::TYPE_GATEWAY_FEE;
        $checkout = $entry->checkout;
        $order = $entry->order;

        $code = $checkout?->code ?? $order?->order_code ?? ($isExpense ? 'EXP-' . $entry->source_id : '-');
        $customer = $checkout?->user?->name ?? $order?->customer_name ?? $order?->user?->name ?? '-';
        $services = $isExpense
            ? trim(($expense?->category?->name ?? 'Pengeluaran') . ' — ' . ($expense?->description ?? ''))
            : ($checkout ? $checkout->orders->map->serviceName()->implode(', ') : ($order?->serviceName() ?? '-'));

        return new Row([
            new DateTimeCell($this->dateOf($entry->business_date->toDateString()), $this->date),
            new DateTimeCell(DateTimeImmutable::createFromInterface($entry->occurred_at)->setTimezone($tz), $this->dateTime),
            $this->text($code),
            $this->text($entry->channel ? ucfirst($entry->channel) : '-'),
            $this->text($customer),
            $this->text($services),
            $this->text($entry->payment_method ? $this->methodLabel($entry->payment_method) : '-'),
            $this->text(FinancialEntry::TYPE_LABELS[$entry->entry_type] ?? $entry->entry_type),
            new NumericCell($isFee ? 0 : $entry->amount, $this->rupiah),
            new NumericCell($isFee ? $entry->amount : 0, $this->rupiah),
            new NumericCell($entry->amount, $this->rupiah),
            $this->text($entry->creator?->name ?? 'Sistem'),
        ]);
    }

    /**
     * @param  array<string, array{summary: int, detail: int, difference: int}>  $controls
     */
    protected function controlSheet(Writer $writer, array $controls): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Kontrol');
        $sheet->setColumnWidth(62, 1);
        $sheet->setColumnWidthForRange(18, 2, 4);
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));

        $writer->addRow($this->headerRow(['Pemeriksaan', 'Ringkasan', 'Detail', 'Selisih']));

        foreach ($controls as $label => $control) {
            $style = str_starts_with($label, 'Jumlah entri') ? $this->count : $this->rupiah;
            $writer->addRow(new Row([
                new StringCell($label),
                new NumericCell($control['summary'], $style),
                new NumericCell($control['detail'], $style),
                new NumericCell($control['difference'], $style),
            ]));
        }

        $writer->addRow(new Row([
            new StringCell('Selisih', $this->header),
            new EmptyCell(null),
            new EmptyCell(null),
            new NumericCell(array_sum(array_map(fn ($c) => abs($c['difference']), $controls)), $this->rupiah),
        ]));
    }

    protected function entries(ReportFilter $filter): Builder
    {
        return $this->reports->detailQuery($filter)->reorder();
    }

    /**
     * @param  list<string>  $titles
     */
    protected function headerRow(array $titles): Row
    {
        return new Row(array_map(fn ($t) => new StringCell($t, $this->header), $titles));
    }

    protected function text(?string $value): Cell
    {
        $value = self::escape($value);

        return $value === '' ? new EmptyCell(null) : new StringCell($value);
    }

    protected function dateOf(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone(BusinessCalendar::timezone()));
    }

    protected function methodLabel(string $method): string
    {
        return config("finance.outlet_methods.{$method}.label") ?? (TransactionDirectory::APP_METHODS[$method] ?? $method);
    }
}
