<?php

namespace App\Services;

use App\Data\ReportFilter;
use App\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan admin. HANYA membaca ledger (financial_entries); semua agregasi GROUP BY di database
 * berdasarkan business_date (tanggal bisnis zona outlet). Nominal integer rupiah.
 *
 *   bruto        = Σ sale
 *   void         = Σ void_reversal (negatif)
 *   refund       = Σ refund (negatif)
 *   biaya        = Σ gateway_fee (negatif, estimasi)
 *   neto         = bruto + void + refund + biaya
 *   pengeluaran  = Σ expense (negatif; hanya di Gabungan karena tidak terikat saluran)
 *   laba         = neto + pengeluaran
 */
class FinancialReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(ReportFilter $filter): array
    {
        $kpi = $this->kpi($filter);

        return [
            'filter'  => $filter,
            'kpi'     => $kpi,
            'methods' => $this->byMethod($filter),
            'trend'   => $this->trend($filter),
            'channels' => $this->channelComparison($filter),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function kpi(ReportFilter $filter): array
    {
        $sums = $this->scoped($filter)
            ->selectRaw('entry_type, SUM(amount) AS total, COUNT(*) AS n')
            ->groupBy('entry_type')
            ->get()
            ->keyBy('entry_type');

        $sum = fn (string $type) => (int) ($sums[$type]->total ?? 0);
        $gross = $sum(FinancialEntry::TYPE_SALE);
        $transactions = (int) ($sums[FinancialEntry::TYPE_SALE]->n ?? 0);
        $void = $sum(FinancialEntry::TYPE_VOID_REVERSAL);
        $refund = $sum(FinancialEntry::TYPE_REFUND);
        $fee = $sum(FinancialEntry::TYPE_GATEWAY_FEE);
        $expense = $sum(FinancialEntry::TYPE_EXPENSE);
        $net = $gross + $void + $refund + $fee;

        return [
            'gross'        => $gross,
            'void'         => $void,
            'refund'       => $refund,
            'gateway_fee'  => $fee,
            'net'          => $net,
            'expense'      => $expense,
            'profit'       => $net + $expense,
            'transactions' => $transactions,
            'average'      => $transactions > 0 ? intdiv($gross, $transactions) : 0,
        ];
    }

    /**
     * Rincian per metode bayar: penjualan, koreksi (void/refund), biaya, dan neto per metode.
     *
     * @return list<array{method: string, gross: int, corrections: int, fee: int, net: int, count: int}>
     */
    public function byMethod(ReportFilter $filter): array
    {
        return $this->scoped($filter)
            ->where('entry_type', '!=', FinancialEntry::TYPE_EXPENSE)
            ->selectRaw("COALESCE(payment_method, '-') AS method")
            ->selectRaw("SUM(CASE WHEN entry_type = 'sale' THEN amount ELSE 0 END) AS gross")
            ->selectRaw("SUM(CASE WHEN entry_type IN ('void_reversal', 'refund') THEN amount ELSE 0 END) AS corrections")
            ->selectRaw("SUM(CASE WHEN entry_type = 'gateway_fee' THEN amount ELSE 0 END) AS fee")
            ->selectRaw('SUM(amount) AS net')
            ->selectRaw("SUM(CASE WHEN entry_type = 'sale' THEN 1 ELSE 0 END) AS cnt")
            ->groupBy('method')
            ->orderByDesc('gross')
            ->get()
            ->map(fn ($row) => [
                'method'      => (string) $row->method,
                'gross'       => (int) $row->gross,
                'corrections' => (int) $row->corrections,
                'fee'         => (int) $row->fee,
                'net'         => (int) $row->net,
                'count'       => (int) $row->cnt,
            ])
            ->all();
    }

    /**
     * Tren pendapatan neto vs pengeluaran per bucket; bucket tanpa transaksi = 0.
     *
     * @return list<array{key: string, label: string, income: int, expense: int}>
     */
    public function trend(ReportFilter $filter): array
    {
        $bucket = $this->bucketExpression($filter->bucket);

        $rows = $this->scoped($filter)
            ->selectRaw("{$bucket} AS bucket_key")
            ->selectRaw("SUM(CASE WHEN entry_type <> 'expense' THEN amount ELSE 0 END) AS income")
            ->selectRaw("SUM(CASE WHEN entry_type = 'expense' THEN amount ELSE 0 END) AS expense")
            ->groupBy('bucket_key')
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket_key);

        return array_map(fn (string $key) => [
            'key'     => $key,
            'label'   => $filter->bucketLabel($key),
            'income'  => (int) ($rows[$key]->income ?? 0),
            'expense' => -(int) ($rows[$key]->expense ?? 0),
        ], $filter->bucketKeys());
    }

    /**
     * Pendapatan neto per bucket untuk Outlet vs Online (abaikan filter saluran).
     *
     * @return list<array{key: string, label: string, outlet: int, online: int}>
     */
    public function channelComparison(ReportFilter $filter): array
    {
        $bucket = $this->bucketExpression($filter->bucket);

        $rows = $this->range($filter)
            ->whereNotNull('channel')
            ->where('entry_type', '!=', FinancialEntry::TYPE_EXPENSE)
            ->selectRaw("{$bucket} AS bucket_key, channel, SUM(amount) AS net")
            ->groupBy('bucket_key', 'channel')
            ->get()
            ->groupBy(fn ($row) => (string) $row->bucket_key);

        return array_map(function (string $key) use ($rows, $filter) {
            $byChannel = ($rows[$key] ?? collect())->pluck('net', 'channel');

            return [
                'key'    => $key,
                'label'  => $filter->bucketLabel($key),
                'outlet' => (int) ($byChannel['outlet'] ?? 0),
                'online' => (int) ($byChannel['online'] ?? 0),
            ];
        }, $filter->bucketKeys());
    }

    /**
     * Detail entri ledger untuk export (1 baris per entri), urut tanggal bisnis lalu id.
     */
    public function detailQuery(ReportFilter $filter): Builder
    {
        return $this->scoped($filter)
            ->with(['checkout.user:id,name', 'checkout.orders:id,checkout_id,service_name_snapshot,service_id,machine_qty', 'order.user:id,name', 'creator:id,name'])
            ->orderBy('business_date')
            ->orderBy('id');
    }

    public function countEntries(ReportFilter $filter): int
    {
        return $this->scoped($filter)->count();
    }

    /**
     * Entri dalam rentang + saluran. Gabungan = semua (termasuk pengeluaran); Outlet/Online = saluran itu saja.
     */
    protected function scoped(ReportFilter $filter): Builder
    {
        return $this->range($filter)
            ->when($filter->channel !== 'gabungan', fn ($q) => $q->where('channel', $filter->channel));
    }

    protected function range(ReportFilter $filter): Builder
    {
        return FinancialEntry::query()
            ->whereBetween('business_date', [$filter->from->toDateString(), $filter->to->toDateString()]);
    }

    /**
     * Ekspresi SQL awal bucket dari business_date (minggu ISO: Senin).
     */
    protected function bucketExpression(string $bucket): string
    {
        return match ($bucket) {
            'harian'   => 'business_date',
            'mingguan' => 'DATE_SUB(business_date, INTERVAL WEEKDAY(business_date) DAY)',
            'bulanan'  => "DATE_FORMAT(business_date, '%Y-%m-01')",
        };
    }
}
