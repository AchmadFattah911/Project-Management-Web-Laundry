@extends('layouts.dashboard')

@section('title', 'Transaksi')

@section('content')
@use('App\Support\TransactionLabels', 'L')
@use('App\Services\TransactionDirectory', 'D')
@php
    $tz = \App\Support\BusinessCalendar::timezone();
    $statusOptions = $tab === D::TAB_OUTLET
        ? D::OUTLET_STATUSES
        : ($audit ? D::AUDIT_STATUSES : \App\Models\Order::STATUS_LABELS + ['dibatalkan' => 'Dibatalkan']);
    $methodOptions = $tab === D::TAB_OUTLET
        ? collect(config('finance.outlet_methods'))->map(fn ($m) => $m['label'])->all()
        : D::APP_METHODS;
@endphp
<div class="space-y-6">
    <x-panel.header title="Transaksi" subtitle="Outlet: dibuat di kasir. Aplikasi: dari website, hanya yang sudah lunas.">
        @can('transaksi.outlet.create')
            <a href="{{ route('panel.transaksi.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs">
                <x-icon name="plus" class="w-4 h-4" />
                <span>Transaksi Outlet Baru</span>
            </a>
        @endcan
    </x-panel.header>

    {{-- Tab saluran --}}
    <nav class="flex gap-2 border-b border-slate-200" aria-label="Saluran transaksi">
        @foreach ([D::TAB_OUTLET => 'Outlet', D::TAB_APP => 'Aplikasi'] as $key => $label)
            @continue($key === D::TAB_APP && ! auth()->user()->can('transaksi.online.view'))
            <a href="{{ route('panel.transaksi.index', ['tab' => $key]) }}"
               @if ($tab === $key && ! $audit) aria-current="page" @endif
               class="px-4 py-2.5 -mb-px border-b-2 text-sm font-bold {{ $tab === $key && ! $audit ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
        @endforeach
        @can('transaksi.audit_pembayaran.view')
            <a href="{{ route('panel.transaksi.index', ['tab' => D::TAB_APP, 'audit' => 1]) }}"
               @if ($audit) aria-current="page" @endif
               class="ml-auto px-4 py-2.5 -mb-px border-b-2 text-xs font-bold {{ $audit ? 'border-amber-500 text-amber-700' : 'border-transparent text-slate-400 hover:text-slate-700' }}">Audit pembayaran</a>
        @endcan
    </nav>

    @if ($audit)
        <x-alert type="warning" message="Mode audit (read-only): transaksi online yang belum atau tidak lunas. Tidak tampil untuk pegawai." />
    @endif

    <x-card padding="p-4">
        <form method="GET" action="{{ route('panel.transaksi.index') }}" class="grid grid-cols-2 lg:grid-cols-12 gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}" />
            @if ($audit)<input type="hidden" name="audit" value="1" />@endif
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Kode, nama, atau nomor HP" class="col-span-2 lg:col-span-4 rounded-xl border-slate-200 text-sm" />
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Dari tanggal" class="lg:col-span-2 rounded-xl border-slate-200 text-sm" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Sampai tanggal" class="lg:col-span-2 rounded-xl border-slate-200 text-sm" />
            <select name="status" aria-label="Status" class="lg:col-span-2 rounded-xl border-slate-200 text-sm">
                <option value="">Semua status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @unless ($audit)
                <select name="method" aria-label="Metode bayar" class="lg:col-span-1 rounded-xl border-slate-200 text-sm">
                    <option value="">Metode</option>
                    @foreach ($methodOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['method'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endunless
            <button type="submit" class="lg:col-span-1 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs">Filter</button>
        </form>
    </x-card>

    @if ($transactions->isEmpty())
        <x-card padding="p-0">
            <x-empty-state title="Tidak ada transaksi" description="Tidak ada transaksi yang cocok dengan filter ini." />
        </x-card>
    @else
        <x-table :headers="['Kode', 'Waktu', 'Pelanggan', 'Layanan', 'Total', 'Metode', 'Status', '']">
            @foreach ($transactions as $trx)
                @php [$label, $variant] = L::status($trx); @endphp
                <tr class="hover:bg-slate-50/60" data-trx="{{ $trx->code }}">
                    <td class="px-5 py-3 font-mono text-xs font-bold text-slate-900">{{ $trx->code }}</td>
                    <td class="px-5 py-3 text-xs whitespace-nowrap">{{ $trx->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3 text-xs">
                        <p class="font-bold text-slate-800">{{ $trx->user?->name ?? '-' }}</p>
                        <p class="text-slate-500">{{ $trx->contact_phone }}</p>
                    </td>
                    <td class="px-5 py-3 text-xs">
                        @foreach ($trx->orders->take(2) as $order)
                            <p>{{ $order->serviceName() }} &times; {{ $order->machine_qty }} <x-badge :status="$order->status" size="sm" :dot="false" /></p>
                        @endforeach
                        @if ($trx->orders->count() > 2)<p class="text-slate-500">+{{ $trx->orders->count() - 2 }} lainnya</p>@endif
                    </td>
                    <td class="px-5 py-3 text-xs font-bold whitespace-nowrap">{{ L::rupiah($trx->total_amount) }}</td>
                    <td class="px-5 py-3 text-xs">{{ L::method($trx) }}</td>
                    <td class="px-5 py-3"><x-badge :variant="$variant" size="sm">{{ $label }}</x-badge></td>
                    <td class="px-5 py-3 text-right"><a href="{{ route('panel.transaksi.show', $trx) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">Detail</a></td>
                </tr>
            @endforeach
        </x-table>

        {{ $transactions->links() }}
    @endif
</div>
@endsection
