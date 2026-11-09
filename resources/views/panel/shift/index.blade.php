@extends('layouts.dashboard')

@section('title', 'Kas & Shift')

@section('content')
@use('App\Models\CashShift', 'CashShift')
@use('App\Support\TransactionLabels', 'L')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<div class="space-y-6">
    <x-panel.header title="Kas & Shift" subtitle="Buka shift dengan saldo awal laci, tutup dengan menghitung uang fisik. Selisih tercatat dan diverifikasi admin." />

    @if ($active)
        <x-card title="Shift aktif #{{ $active->id }}" subtitle="Dibuka {{ $active->opened_at->timezone($tz)->format('d/m/Y H:i') }} WIB">
            <div class="grid gap-4 sm:grid-cols-4 mb-6">
                <x-stat-card title="Saldo awal" :value="L::rupiah($activeSummary['opening'])" color="slate" />
                <x-stat-card title="Tunai masuk" :value="L::rupiah($activeSummary['cash_in'])" color="emerald" />
                <x-stat-card title="Tunai keluar (void/refund)" :value="L::rupiah($activeSummary['cash_out'])" color="rose" />
                <x-stat-card title="Seharusnya di laci" :value="L::rupiah($activeSummary['expected'])" color="sky" />
            </div>

            @can('shift.close')
                <x-panel.form :action="route('panel.shift.close', $active)" class="space-y-4" confirm="Tutup shift sekarang? Pastikan uang di laci sudah dihitung.">
                    <div x-data="{ mode: '{{ old('mode', 'pecahan') }}', counts: {}, total() { return Object.entries(this.counts).reduce((s, [v, c]) => s + Number(v) * (parseInt(c) || 0), 0) } }" class="space-y-4">
                        <div class="flex gap-4 text-xs">
                            <label class="flex items-center gap-2"><input type="radio" name="mode" value="pecahan" x-model="mode" /> Hitung per pecahan</label>
                            <label class="flex items-center gap-2"><input type="radio" name="mode" value="total" x-model="mode" /> Isi total saja</label>
                        </div>
                        <div x-show="mode === 'pecahan'" class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            @foreach (config('finance.denominations') as $value)
                                <label class="text-xs"><span class="font-bold text-slate-700">{{ L::rupiah($value) }}</span>
                                    <input type="number" min="0" name="denominations[{{ $value }}]" x-model="counts[{{ $value }}]" :disabled="mode !== 'pecahan'" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="0" /></label>
                            @endforeach
                            <p class="col-span-full text-xs font-bold text-slate-700">Total dihitung: <span x-text="'Rp ' + total().toLocaleString('id-ID')"></span></p>
                        </div>
                        <label x-show="mode === 'total'" x-cloak class="block text-xs max-w-xs"><span class="font-bold text-slate-700">Total uang fisik</span>
                            <input type="number" min="0" name="counted_total" :disabled="mode !== 'total'" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                        <label class="block text-xs"><span class="font-bold text-slate-700">Catatan (opsional)</span>
                            <input type="text" name="note" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                        @error('denominations')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        @error('counted_total')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        <x-panel.submit variant="danger">Tutup Shift</x-panel.submit>
                    </div>
                </x-panel.form>
            @endcan
        </x-card>
    @elsecan('shift.open')
        <x-card title="Buka Shift">
            <x-panel.form :action="route('panel.shift.open')" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <x-panel.field label="Saldo awal laci (Rp)" name="opening_balance" type="number" required min="0" />
                <x-panel.submit>Buka Shift</x-panel.submit>
            </x-panel.form>
            <p class="mt-3 text-[11px] text-slate-500">Satu shift aktif per petugas. Transaksi tunai "bayar sekarang" hanya bisa dicatat saat shift buka.</p>
        </x-card>
    @endif

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900">{{ auth()->user()->can('shift.view_all') ? 'Semua Shift' : 'Riwayat Shift Saya' }}</h2>
            <form method="GET" class="flex gap-2">
                <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs" aria-label="Status shift">
                    <option value="">Semua status</option>
                    @foreach (CashShift::STATUS_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if ($shifts->isEmpty())
            <x-card padding="p-0"><x-empty-state title="Belum ada shift" description="Shift yang dibuka akan tercatat di sini." /></x-card>
        @else
            <x-table :headers="['Shift', 'Petugas', 'Buka', 'Tutup', 'Seharusnya', 'Fisik', 'Selisih', 'Status', '']">
                @foreach ($shifts as $shift)
                    <tr data-shift="{{ $shift->id }}">
                        <td class="px-5 py-3 text-xs font-bold">#{{ $shift->id }}</td>
                        <td class="px-5 py-3 text-xs">{{ $shift->user?->name }}</td>
                        <td class="px-5 py-3 text-xs">{{ $shift->opened_at->timezone($tz)->format('d/m H:i') }}</td>
                        <td class="px-5 py-3 text-xs">{{ $shift->closed_at?->timezone($tz)->format('d/m H:i') ?? '-' }}</td>
                        <td class="px-5 py-3 text-xs">{{ $shift->expected_cash !== null ? L::rupiah($shift->expected_cash) : '-' }}</td>
                        <td class="px-5 py-3 text-xs">{{ $shift->counted_cash !== null ? L::rupiah($shift->counted_cash) : '-' }}</td>
                        <td class="px-5 py-3 text-xs font-bold {{ ($shift->difference ?? 0) === 0 ? 'text-slate-700' : 'text-rose-600' }}">{{ $shift->difference !== null ? ($shift->difference < 0 ? '-' : '') . L::rupiah(abs($shift->difference)) : '-' }}</td>
                        <td class="px-5 py-3"><x-badge :variant="['open' => 'primary', 'closed' => 'warning', 'verified' => 'success'][$shift->status]" size="sm">{{ CashShift::STATUS_LABELS[$shift->status] }}</x-badge></td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('panel.shift.show', $shift) }}" class="text-xs font-bold text-sky-600">Detail</a></td>
                    </tr>
                @endforeach
            </x-table>
            {{ $shifts->links() }}
        @endif
    </section>
</div>
@endsection
