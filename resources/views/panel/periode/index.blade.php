@extends('layouts.dashboard')

@section('title', 'Tutup Buku')

@section('content')
<div class="space-y-6">
    <x-panel.header title="Tutup Buku" subtitle="Tanggal bisnis di periode terkunci tidak bisa menerima void, refund manual, atau pengeluaran baru. Koreksi dicatat di periode berjalan." />

    <x-card title="Kunci Periode">
        <x-panel.form :action="route('panel.periode.store')" :token="false" class="grid sm:grid-cols-4 gap-3 items-end" confirm="Kunci periode ini? Periode yang sudah dikunci tidak bisa dibuka dari aplikasi.">
            <x-panel.field label="Dari" name="period_start" type="date" required />
            <x-panel.field label="Sampai" name="period_end" type="date" required :max="\App\Support\BusinessCalendar::today()" />
            <x-panel.field label="Catatan" name="note" />
            <x-panel.submit variant="danger">Tutup Buku</x-panel.submit>
        </x-panel.form>
    </x-card>

    @if ($periods->isEmpty())
        <x-card padding="p-0"><x-empty-state title="Belum ada periode terkunci" description="Periode yang ditutup buku akan tampil di sini." /></x-card>
    @else
        <x-table :headers="['Periode', 'Catatan', 'Ditutup oleh', 'Waktu']">
            @foreach ($periods as $period)
                <tr>
                    <td class="px-5 py-3 text-xs font-bold">{{ $period->period_start->format('d/m/Y') }} – {{ $period->period_end->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-xs">{{ $period->note ?? '-' }}</td>
                    <td class="px-5 py-3 text-xs">{{ $period->closer?->name ?? '-' }}</td>
                    <td class="px-5 py-3 text-xs">{{ $period->created_at->timezone(\App\Support\BusinessCalendar::timezone())->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </x-table>
        {{ $periods->links() }}
    @endif
</div>
@endsection
