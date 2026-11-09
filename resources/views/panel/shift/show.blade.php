@extends('layouts.dashboard')

@section('title', 'Shift #' . $shift->id)

@section('content')
@use('App\Models\CashShift', 'CashShift')
@use('App\Models\FinancialEntry', 'FinancialEntry')
@use('App\Support\TransactionLabels', 'L')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<div class="space-y-6">
    <div><a href="{{ route('panel.shift.index') }}" class="text-xs font-bold text-sky-600">&larr; Kas & Shift</a></div>

    <x-panel.header :title="'Shift #' . $shift->id . ' · ' . $shift->user?->name" :subtitle="'Buka ' . $shift->opened_at->timezone($tz)->format('d/m/Y H:i') . ($shift->closed_at ? ' · Tutup ' . $shift->closed_at->timezone($tz)->format('d/m/Y H:i') : '') . ' WIB'">
        <x-badge :variant="['open' => 'primary', 'closed' => 'warning', 'verified' => 'success'][$shift->status]">{{ CashShift::STATUS_LABELS[$shift->status] }}</x-badge>
    </x-panel.header>

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <x-stat-card title="Saldo awal" :value="L::rupiah($summary['opening'])" color="slate" />
        <x-stat-card title="Tunai masuk" :value="L::rupiah($summary['cash_in'])" color="emerald" />
        <x-stat-card title="Tunai keluar" :value="L::rupiah($summary['cash_out'])" color="rose" />
        <x-stat-card title="Seharusnya" :value="L::rupiah($shift->expected_cash ?? $summary['expected'])" color="sky" />
        <x-stat-card title="Uang fisik" :value="$shift->counted_cash !== null ? L::rupiah($shift->counted_cash) : '-'" color="violet" />
        <x-stat-card title="Selisih" :value="$shift->difference !== null ? ($shift->difference < 0 ? '-' : '') . L::rupiah(abs($shift->difference)) : '-'" :color="($shift->difference ?? 0) === 0 ? 'emerald' : 'rose'" />
    </div>

    @if ($shift->denominations)
        <x-card title="Hitungan per pecahan">
            <ul class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                @foreach ($shift->denominations as $value => $count)
                    <li>{{ L::rupiah($value) }} &times; {{ $count }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($shift->close_note || $shift->verify_note)
        <x-card padding="p-4">
            @if ($shift->close_note)<p class="text-xs">Catatan tutup: {{ $shift->close_note }}</p>@endif
            @if ($shift->verify_note)<p class="text-xs mt-1">Catatan verifikasi ({{ $shift->verifier?->name }}): {{ $shift->verify_note }}</p>@endif
        </x-card>
    @endif

    @if ($shift->status === CashShift::STATUS_CLOSED)
        @can('shift.verify')
            <x-card title="Verifikasi Admin">
                <x-panel.form :action="route('panel.shift.verify', $shift)" :token="false" class="flex flex-col sm:flex-row gap-3 sm:items-end" confirm="Tandai shift ini terverifikasi?">
                    <x-panel.field label="Catatan (opsional)" name="note" />
                    <x-panel.submit variant="success">Verifikasi</x-panel.submit>
                </x-panel.form>
            </x-card>
        @endcan
    @endif

    <section class="space-y-3">
        <h2 class="text-sm font-extrabold text-slate-900">Transaksi di shift ini</h2>
        @if ($entries->isEmpty())
            <x-card padding="p-0"><x-empty-state title="Belum ada transaksi" description="Transaksi yang tercatat ke shift ini akan tampil di sini." /></x-card>
        @else
            <x-table :headers="['Waktu', 'Kode', 'Jenis', 'Metode', 'Nominal']">
                @foreach ($entries as $entry)
                    <tr>
                        <td class="px-5 py-3 text-xs">{{ $entry->occurred_at->timezone($tz)->format('d/m H:i') }}</td>
                        <td class="px-5 py-3 text-xs font-mono">{{ $entry->checkout?->code ?? '-' }}</td>
                        <td class="px-5 py-3 text-xs">{{ FinancialEntry::TYPE_LABELS[$entry->entry_type] }}</td>
                        <td class="px-5 py-3 text-xs">{{ config("finance.outlet_methods.{$entry->payment_method}.label", $entry->payment_method) }}</td>
                        <td class="px-5 py-3 text-xs font-bold {{ $entry->amount < 0 ? 'text-rose-600' : '' }}">{{ $entry->amount < 0 ? '-' : '' }}{{ L::rupiah(abs($entry->amount)) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </section>
</div>
@endsection
