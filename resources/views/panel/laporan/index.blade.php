@extends('layouts.dashboard')

@section('title', 'Laporan Keuangan')

@section('content')
@use('App\Data\ReportFilter', 'ReportFilter')
@use('App\Support\TransactionLabels', 'L')
@php
    $filter = $report['filter'];
    $kpi = $report['kpi'];
    $signed = fn (int $v) => ($v < 0 ? '-' : '') . L::rupiah(abs($v));
    $methodLabel = fn (string $m) => config("finance.outlet_methods.{$m}.label") ?? (\App\Services\TransactionDirectory::APP_METHODS[$m] ?? $m);
    $query = $filter->toQuery();
@endphp
<div class="space-y-6">
    <x-panel.header title="Laporan Keuangan" :subtitle="'Sumber: ledger keuangan · zona waktu ' . \App\Support\BusinessCalendar::timezone() . ' · ' . $filter->from->format('d/m/Y') . ' s.d. ' . $filter->to->format('d/m/Y')">
        @can('laporan.export')
            @if (Route::has('panel.ekspor.store'))
                <x-panel.form :action="route('panel.ekspor.store')" :token="false">
                    @foreach ($query as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}" />@endforeach
                    <x-panel.submit variant="success">Export Excel</x-panel.submit>
                </x-panel.form>
            @endif
        @endcan
    </x-panel.header>

    {{-- Tab saluran --}}
    <nav class="flex gap-2 border-b border-slate-200" aria-label="Saluran">
        @foreach (ReportFilter::CHANNELS as $key => $label)
            <a href="{{ route('panel.laporan.index', ['kanal' => $key] + $query) }}" @if ($filter->channel === $key) aria-current="page" @endif
               class="px-4 py-2.5 -mb-px border-b-2 text-sm font-bold {{ $filter->channel === $key ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <input type="hidden" name="kanal" value="{{ $filter->channel }}" />
            <label class="lg:col-span-3 text-xs"><span class="font-bold text-slate-700">Periode</span>
                <select name="periode" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach (['harian' => 'Harian', 'mingguan' => 'Mingguan (Senin–Minggu)', 'bulanan' => 'Bulanan'] as $key => $label)
                        <option value="{{ $key }}" @selected($filter->bucket === $key)>{{ $label }}</option>
                    @endforeach
                </select></label>
            <label class="lg:col-span-3 text-xs"><span class="font-bold text-slate-700">Dari</span>
                <input type="date" name="dari" value="{{ $filter->custom ? $filter->from->toDateString() : '' }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
            <label class="lg:col-span-3 text-xs"><span class="font-bold text-slate-700">Sampai</span>
                <input type="date" name="sampai" value="{{ $filter->custom ? $filter->to->toDateString() : '' }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
            <button type="submit" class="lg:col-span-3 px-4 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs">Tampilkan</button>
        </form>
        <p class="mt-2 text-[11px] text-slate-500">Kosongkan tanggal untuk rentang bawaan: bulan ini (harian), 12 minggu terakhir (mingguan), tahun ini (bulanan).</p>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-kpi>
        <x-stat-card title="Pendapatan Bruto" :value="L::rupiah($kpi['gross'])" color="sky" :hint="$kpi['transactions'] . ' transaksi · rata-rata ' . L::rupiah($kpi['average'])" />
        <x-stat-card title="Void" :value="$signed($kpi['void'])" color="rose" />
        <x-stat-card title="Refund" :value="$signed($kpi['refund'])" color="rose" />
        <x-stat-card title="Biaya Gateway (estimasi)" :value="$signed($kpi['gateway_fee'])" color="amber" />
        <x-stat-card title="Pendapatan Neto" :value="$signed($kpi['net'])" color="emerald" />
        <x-stat-card title="Pengeluaran" :value="$signed($kpi['expense'])" color="violet" :hint="$filter->channel === 'gabungan' ? null : 'Pengeluaran tidak dialokasikan per saluran'" />
        <x-stat-card title="Laba" :value="$signed($kpi['profit'])" :color="$kpi['profit'] >= 0 ? 'emerald' : 'rose'" />
        <x-stat-card title="Jumlah Transaksi" :value="$kpi['transactions']" color="slate" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Tren Pendapatan Neto vs Pengeluaran" class="lg:col-span-2">
            <div class="h-72"><canvas id="trendChart" aria-label="Grafik tren pendapatan dan pengeluaran"></canvas></div>
        </x-card>
        <x-card title="Komposisi Metode Bayar">
            @if (collect($report['methods'])->sum('gross') === 0)
                <x-empty-state title="Belum ada penjualan" description="Tidak ada penjualan pada rentang ini." />
            @else
                <div class="h-72"><canvas id="methodChart" aria-label="Grafik komposisi metode bayar"></canvas></div>
            @endif
        </x-card>
        <x-card title="Outlet vs Online (pendapatan neto)" class="lg:col-span-3">
            <div class="h-64"><canvas id="channelChart" aria-label="Grafik perbandingan outlet dan online"></canvas></div>
        </x-card>
    </div>

    <x-card title="Rincian per Metode Bayar" padding="p-0">
        @if ($report['methods'] === [])
            <x-empty-state title="Tidak ada data" description="Belum ada entri ledger pada rentang dan saluran ini." />
        @else
            <x-table :headers="['Metode', 'Transaksi', 'Bruto', 'Void/Refund', 'Biaya (estimasi)', 'Neto']">
                @foreach ($report['methods'] as $row)
                    <tr data-method="{{ $row['method'] }}">
                        <td class="px-5 py-3 text-xs font-bold">{{ $methodLabel($row['method']) }}</td>
                        <td class="px-5 py-3 text-xs">{{ $row['count'] }}</td>
                        <td class="px-5 py-3 text-xs">{{ L::rupiah($row['gross']) }}</td>
                        <td class="px-5 py-3 text-xs text-rose-600">{{ $signed($row['corrections']) }}</td>
                        <td class="px-5 py-3 text-xs text-amber-700">{{ $signed($row['fee']) }}</td>
                        <td class="px-5 py-3 text-xs font-bold">{{ $signed($row['net']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</div>

@push('scripts')
<script>
    (function () {
        const trend = @js($report['trend']);
        const channels = @js($report['channels']);
        const methods = @js(collect($report['methods'])->filter(fn ($m) => $m['gross'] > 0)->map(fn ($m) => ['label' => $methodLabel($m['method']), 'value' => $m['gross']])->values());
        const rupiah = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
        const money = { ticks: { callback: (v) => rupiah(v) } };

        function draw() {
            if (typeof Chart === 'undefined') return;
            const make = (id, config) => { const el = document.getElementById(id); if (el) new Chart(el, config); };

            make('trendChart', { type: 'bar', data: { labels: trend.map(b => b.label), datasets: [
                { label: 'Pendapatan neto', data: trend.map(b => b.income), backgroundColor: 'rgba(2,132,199,.85)', borderRadius: 6 },
                { label: 'Pengeluaran', data: trend.map(b => b.expense), backgroundColor: 'rgba(225,29,72,.75)', borderRadius: 6 },
            ] }, options: { maintainAspectRatio: false, scales: { y: money }, plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + rupiah(c.raw) } } } } });

            make('channelChart', { type: 'line', data: { labels: channels.map(b => b.label), datasets: [
                { label: 'Outlet', data: channels.map(b => b.outlet), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.15)', fill: true, tension: .3 },
                { label: 'Online', data: channels.map(b => b.online), borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.12)', fill: true, tension: .3 },
            ] }, options: { maintainAspectRatio: false, scales: { y: money } } });

            make('methodChart', { type: 'doughnut', data: { labels: methods.map(m => m.label), datasets: [{ data: methods.map(m => m.value),
                backgroundColor: ['#0284c7', '#10b981', '#f59e0b', '#7c3aed', '#e11d48', '#64748b', '#14b8a6'] }] },
                options: { maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (c) => c.label + ': ' + rupiah(c.raw) } } } } });
        }

        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', draw) : draw();
    })();
</script>
@endpush
@endsection
