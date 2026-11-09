@extends('layouts.dashboard')

@section('title', 'Ekspor Saya')

@section('content')
@use('App\Models\FinanceExport', 'FinanceExport')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<div class="space-y-6">
    <x-panel.header title="Ekspor Saya" subtitle="Riwayat export Excel laporan keuangan. Rentang besar diproses di latar belakang; muat ulang halaman untuk melihat statusnya.">
        <a href="{{ route('panel.laporan.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Ke Laporan Keuangan</a>
    </x-panel.header>

    @if ($exports->isEmpty())
        <x-card padding="p-0"><x-empty-state title="Belum ada export" description="Buat export dari halaman Laporan Keuangan." /></x-card>
    @else
        <x-table :headers="['Dibuat', 'Saluran', 'Periode', 'Rentang', 'Baris', 'Status', '']">
            @foreach ($exports as $export)
                <tr data-export="{{ $export->id }}">
                    <td class="px-5 py-3 text-xs">{{ $export->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3 text-xs">{{ ucfirst($export->filters['kanal'] ?? '-') }}</td>
                    <td class="px-5 py-3 text-xs">{{ ucfirst($export->filters['periode'] ?? '-') }}</td>
                    <td class="px-5 py-3 text-xs">{{ $export->filters['dari'] ?? '' }} s.d. {{ $export->filters['sampai'] ?? '' }}</td>
                    <td class="px-5 py-3 text-xs">{{ $export->rows ?? '-' }}</td>
                    <td class="px-5 py-3">
                        <x-badge :variant="['queued' => 'warning', 'processing' => 'primary', 'done' => 'success', 'failed' => 'danger'][$export->status]" size="sm">{{ FinanceExport::STATUS_LABELS[$export->status] }}</x-badge>
                        @if ($export->error)<p class="mt-1 text-[11px] text-rose-600 max-w-xs">{{ $export->error }}</p>@endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if ($export->status === FinanceExport::STATUS_DONE)
                            <a href="{{ route('panel.ekspor.download', $export) }}" class="text-xs font-bold text-sky-600">Unduh</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        {{ $exports->links() }}
    @endif
</div>
@endsection
