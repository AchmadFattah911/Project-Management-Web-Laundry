@extends('layouts.dashboard')

@section('title', 'Laporan Keuangan')

@php
    use App\Services\FinanceReportService;

    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $step = ['harian' => 'addDay', 'mingguan' => 'addWeek', 'bulanan' => 'addMonth'][$report['period']];
    $back = ['harian' => 'subDay', 'mingguan' => 'subWeek', 'bulanan' => 'subMonth'][$report['period']];
    $params = ['periode' => $report['period'], 'kanal' => $report['channel'], 'tanggal' => $report['date']->format('Y-m-d')];
    $hasIssues = $report['mismatches']->isNotEmpty() || $report['refunds']->isNotEmpty() || $report['unproven']->isNotEmpty();
@endphp

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Laporan Keuangan
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Audit uang yang benar-benar masuk, dihitung dari pembayaran lunas ({{ FinanceReportService::timezone() }})
            </p>
        </div>

        <a
            href="{{ route('pegawai.keuangan.export', $params) }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors self-start sm:self-auto"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V3" />
            </svg>
            <span>Unduh Rekap (CSV)</span>
        </a>
    </div>

    <!-- Filter -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('pegawai.keuangan.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-3">
                <label for="periode" class="sr-only">Periode</label>
                <select id="periode" name="periode" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500">
                    @foreach (FinanceReportService::PERIODS as $value => $label)
                        <option value="{{ $value }}" @selected($report['period'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label for="kanal" class="sr-only">Saluran</label>
                <select id="kanal" name="kanal" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500">
                    @foreach (FinanceReportService::CHANNELS as $value => $label)
                        <option value="{{ $value }}" @selected($report['channel'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-4">
                <label for="tanggal" class="sr-only">Tanggal</label>
                <input
                    id="tanggal"
                    type="date"
                    name="tanggal"
                    value="{{ $report['date']->format('Y-m-d') }}"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                />
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs transition-colors">
                    Tampilkan
                </button>
            </div>
        </form>

        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
            <a href="{{ route('pegawai.keuangan.index', ['tanggal' => $report['date']->{$back}()->format('Y-m-d')] + $params) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">&larr; Sebelumnya</a>
            <span class="text-xs sm:text-sm font-extrabold text-slate-900 text-center">{{ $report['label'] }}</span>
            <a href="{{ route('pegawai.keuangan.index', ['tanggal' => $report['date']->{$step}()->format('Y-m-d')] + $params) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">Berikutnya &rarr;</a>
        </div>
    </x-card>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card title="Total Pemasukan" :value="$rupiah($report['total'])" hint="Saluran {{ FinanceReportService::CHANNELS[$report['channel']] }}" color="emerald" />
        <x-stat-card title="Transaksi Lunas" :value="$report['count']" hint="Jumlah pembayaran masuk" color="sky" />
        <x-stat-card title="Rata-rata Transaksi" :value="$rupiah($report['average'])" hint="Per pembayaran" color="violet" />
        <x-stat-card title="Perlu Refund" :value="$rupiah($report['refundTotal'])" hint="Bukan pemasukan, harus dikembalikan" color="rose" />
    </div>

    <!-- Hasil audit -->
    @if ($hasIssues)
        <x-card title="Perlu Diperiksa" subtitle="Temuan audit pada periode ini. Laporkan ke admin untuk ditindaklanjuti." padding="p-0">
            <x-table :headers="['Temuan', 'Kode', 'Pelanggan', 'Nominal', 'Keterangan']">
                @foreach ($report['refunds'] as $row)
                    <tr>
                        <td class="px-5 py-3 text-xs font-bold text-rose-700">Bayar ganda</td>
                        <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900">{{ $row['code'] }}</td>
                        <td class="px-5 py-3 text-xs">{{ $row['customer'] }}</td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-900">{{ $rupiah($row['amount']) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">Uang masuk untuk transaksi yang sudah lunas, perlu dikembalikan.</td>
                    </tr>
                @endforeach
                @foreach ($report['unproven'] as $row)
                    <tr>
                        <td class="px-5 py-3 text-xs font-bold text-amber-700">Tanpa bukti</td>
                        <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900">{{ $row['code'] }}</td>
                        <td class="px-5 py-3 text-xs">{{ $row['customer'] }}</td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-900">{{ $rupiah($row['amount']) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">Pembayaran {{ $row['method'] }} tanpa nomor bukti (dicatat {{ $row['cashier'] ?? 'sistem' }}).</td>
                    </tr>
                @endforeach
                @foreach ($report['mismatches'] as $row)
                    <tr>
                        <td class="px-5 py-3 text-xs font-bold text-amber-700">Nominal selisih</td>
                        <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900">{{ $row['code'] }}</td>
                        <td class="px-5 py-3 text-xs">{{ $row['customer'] }}</td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-900">{{ $rupiah($row['amount']) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">Uang masuk {{ $row['difference'] > 0 ? 'lebih' : 'kurang' }} {{ $rupiah(abs($row['difference'])) }} dari nilai pesanan.</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @else
        <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
            Audit bersih: semua pembayaran cocok dengan nilai pesanan, punya bukti, dan tidak ada pembayaran ganda.
        </div>
    @endif

    <!-- Dua pertanyaan audit: keduanya selalu berjumlah sama dengan Total Pemasukan -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ([
            ['Uang Masuk ke Mana?', 'Cocokkan tiap nominal dengan kondisi nyatanya', $report['destinations'], 'bg-sky-500'],
            ['Uang dari Layanan Apa?', 'Uang yang diterima, dibagi per layanan', $report['sources'], 'bg-emerald-500'],
            ...($report['channel'] === 'online' ? [] : [['Diterima Petugas Siapa?', 'Tunai harus sama dengan setoran petugas', $report['cashiers'], 'bg-amber-500']]),
        ] as [$title, $subtitle, $rows, $barColor])
            <x-card :title="$title" :subtitle="$subtitle" padding="p-0">
                <div class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <div class="px-6 py-3.5 space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-slate-900">{{ $row['label'] }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $row['hint'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-extrabold text-slate-900">{{ $rupiah($row['total']) }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $row['share'] }}%</p>
                                </div>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $row['share'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-4 text-xs text-slate-500">Belum ada pemasukan.</p>
                    @endforelse
                    @if ($rows !== $report['cashiers'])
                        <div class="px-6 py-3 flex items-center justify-between bg-slate-50/80 rounded-b-xl">
                            <span class="text-xs font-extrabold text-slate-900">Total Pemasukan</span>
                            <span class="text-xs font-extrabold text-slate-900">{{ $rupiah($report['total']) }}</span>
                        </div>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>

    <!-- Rekap per hari (mingguan & bulanan), rincian transaksi (harian) -->
    @if ($report['period'] !== 'harian')
        <x-card title="Rekap Per Hari" subtitle="Pilih tanggal untuk melihat rincian transaksinya" padding="p-0">
            <x-table :headers="['Tanggal', 'Transaksi', 'Total']">
                @foreach ($report['byDay'] as $day)
                    <tr>
                        <td class="px-5 py-3 text-xs">
                            <a href="{{ route('pegawai.keuangan.index', ['periode' => 'harian', 'kanal' => $report['channel'], 'tanggal' => $day['date']->format('Y-m-d')]) }}" class="font-bold text-sky-600 hover:text-sky-700">
                                {{ $day['date']->translatedFormat('D, d M Y') }}
                            </a>
                        </td>
                        <td class="px-5 py-3 text-xs">{{ $day['count'] }}</td>
                        <td class="px-5 py-3 text-xs font-bold text-slate-900">{{ $rupiah($day['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-slate-50/80">
                    <td class="px-5 py-3 text-xs font-extrabold text-slate-900">Total</td>
                    <td class="px-5 py-3 text-xs font-extrabold text-slate-900">{{ $report['count'] }}</td>
                    <td class="px-5 py-3 text-xs font-extrabold text-slate-900">{{ $rupiah($report['total']) }}</td>
                </tr>
            </x-table>
        </x-card>
    @else
        <x-card title="Rincian Transaksi" padding="p-0">
            @if ($report['transactions']->isNotEmpty())
                <x-table :headers="['Waktu', 'Kode', 'Pelanggan', 'Layanan', 'Saluran', 'Metode', 'Petugas', 'Nominal']">
                    @foreach ($report['transactions'] as $row)
                        <tr>
                            <td class="px-5 py-3 text-xs">{{ $row['time']->format('H:i') }}</td>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900">{{ $row['code'] }}</td>
                            <td class="px-5 py-3 text-xs">{{ $row['customer'] }}</td>
                            <td class="px-5 py-3 text-xs">{{ implode(', ', array_column($row['services'], 'name')) }}</td>
                            <td class="px-5 py-3 text-xs">{{ $row['channel'] }}</td>
                            <td class="px-5 py-3 text-xs">
                                {{ $row['method'] }}
                                @if ($row['cashier'] && $row['reference'])
                                    <span class="block font-mono text-[10px] text-slate-500">{{ $row['reference'] }}</span>
                                @elseif ($row['received'])
                                    <span class="block text-[10px] text-slate-500">Terima {{ $rupiah($row['received']) }}, kembali {{ $rupiah($row['change']) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs">{{ $row['cashier'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-xs font-bold text-slate-900">{{ $rupiah($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @else
                <x-empty-state
                    title="Belum Ada Pemasukan"
                    description="Tidak ada pembayaran lunas pada tanggal dan saluran yang dipilih."
                />
            @endif
        </x-card>
    @endif

</div>
@endsection
