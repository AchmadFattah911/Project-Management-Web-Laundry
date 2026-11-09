@extends('layouts.dashboard')

@section('title', 'Pelanggan ' . $customer->name)

@section('content')
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((int) \App\Support\Rupiah::parse($v), 0, ',', '.');
    $tz = \App\Support\BusinessCalendar::timezone();
@endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('panel.pelanggan.index') }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">&larr; Data Pelanggan</a>
    </div>

    <x-panel.header :title="$customer->name" :subtitle="'ID #' . $customer->id . ' · ' . ($customer->isWalkIn() ? 'Pelanggan outlet (tanpa akun website)' : 'Akun website ' . $customer->email)">
        @can('pelanggan.update')
            @if ($customer->is_active)
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'ubah-pelanggan')" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Ubah Kontak</button>
            @endif
        @endcan
        @can('pelanggan.deactivate')
            @if ($customer->is_active)
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'nonaktif-pelanggan')" class="px-4 py-2.5 rounded-xl border border-rose-200 bg-white text-xs font-bold text-rose-600 hover:bg-rose-50">Nonaktifkan</button>
            @elseif (! $customer->merged_into_id)
                <x-panel.form :action="route('panel.pelanggan.activate', $customer)" :token="false">
                    <input type="hidden" name="reason" value="Diaktifkan kembali oleh admin" />
                    <x-panel.submit variant="success">Aktifkan Kembali</x-panel.submit>
                </x-panel.form>
            @endif
        @endcan
    </x-panel.header>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-stat-card title="Nomor HP" :value="$customer->phone_e164 ? \App\Support\PhoneNumber::display($customer->phone_e164) : ($customer->phone ?: '-')" color="sky" />
        <x-stat-card title="Transaksi Outlet" :value="$totals['outlet'] ?? 0" color="amber" />
        <x-stat-card title="Transaksi Online" :value="$totals['online'] ?? 0" color="violet" />
        <x-stat-card title="Status" :value="$customer->is_active ? 'Aktif' : ($customer->merged_into_id ? 'Digabung ke #' . $customer->merged_into_id : 'Nonaktif')" :color="$customer->is_active ? 'emerald' : 'rose'" />
    </div>

    @if ($customer->customer_notes)
        <x-card padding="p-4"><p class="text-xs text-slate-700"><span class="font-bold">Catatan:</span> {{ $customer->customer_notes }}</p></x-card>
    @endif

    <section class="space-y-3">
        <h2 class="text-sm font-extrabold text-slate-900">Riwayat Transaksi</h2>
        @if ($checkouts->isEmpty() && $legacy->isEmpty())
            <x-card padding="p-0"><x-empty-state title="Belum ada transaksi" description="Transaksi outlet dan online pelanggan ini akan tampil di sini." /></x-card>
        @else
            <x-table :headers="['Kode', 'Saluran', 'Waktu', 'Layanan', 'Total', 'Status']">
                @foreach ($checkouts as $trx)
                    <tr data-trx="{{ $trx->code }}">
                        <td class="px-5 py-3 font-mono text-xs font-bold">{{ $trx->code }}</td>
                        <td class="px-5 py-3 text-xs">{{ $trx->channel === 'outlet' ? 'Outlet' : 'Aplikasi' }}</td>
                        <td class="px-5 py-3 text-xs">{{ $trx->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3 text-xs">{{ $trx->orders->map->serviceName()->implode(', ') }}</td>
                        <td class="px-5 py-3 text-xs font-bold">{{ $rupiah($trx->total_amount) }}</td>
                        <td class="px-5 py-3 text-xs">{{ $trx->voided_at ? 'Void' : str_replace('_', ' ', ucfirst($trx->status)) }}</td>
                    </tr>
                @endforeach
                @foreach ($legacy as $order)
                    <tr data-trx="{{ $order->order_code }}">
                        <td class="px-5 py-3 font-mono text-xs font-bold">{{ $order->order_code }}</td>
                        <td class="px-5 py-3 text-xs">{{ $order->channel === 'outlet' ? 'Outlet (lama)' : 'Aplikasi (lama)' }}</td>
                        <td class="px-5 py-3 text-xs">{{ $order->created_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3 text-xs">{{ $order->serviceName() }}</td>
                        <td class="px-5 py-3 text-xs font-bold">{{ $rupiah($order->total_price) }}</td>
                        <td class="px-5 py-3 text-xs">{{ $order->isPaid() ? 'Lunas' : 'Belum lunas' }}</td>
                    </tr>
                @endforeach
            </x-table>
            {{ $checkouts->links() }}
        @endif
    </section>
</div>

@can('pelanggan.update')
    <x-modal name="ubah-pelanggan" title="Ubah Kontak Pelanggan" maxWidth="md">
        <x-panel.form :action="route('panel.pelanggan.update', $customer)" method="PUT" :token="false" class="space-y-4">
            <input type="hidden" name="_modal" value="ubah-pelanggan" />
            <x-panel.field label="Nama" name="name" :value="$customer->name" required maxlength="100" />
            <x-panel.field label="Nomor HP" name="phone" type="tel" :value="$customer->phone_e164 ? '0' . substr($customer->phone_e164, 3) : $customer->phone" required />
            <label class="block text-xs">
                <span class="font-bold text-slate-700">Catatan</span>
                <textarea name="notes" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('notes', $customer->customer_notes) }}</textarea>
            </label>
            <div class="flex justify-end"><x-panel.submit>Simpan Perubahan</x-panel.submit></div>
        </x-panel.form>
    </x-modal>
@endcan

@can('pelanggan.deactivate')
    <x-modal name="nonaktif-pelanggan" title="Nonaktifkan Pelanggan" maxWidth="md">
        <x-panel.form :action="route('panel.pelanggan.deactivate', $customer)" :token="false" class="space-y-4" confirm="Nonaktifkan pelanggan ini? Riwayat transaksinya tetap tersimpan.">
            <input type="hidden" name="_modal" value="nonaktif-pelanggan" />
            <p class="text-xs text-slate-600">Data tidak dihapus. Pelanggan nonaktif tidak bisa dipilih di transaksi baru dan tidak bisa login.</p>
            <x-panel.field label="Alasan" name="reason" required minlength="10" />
            <div class="flex justify-end"><x-panel.submit variant="danger">Nonaktifkan</x-panel.submit></div>
        </x-panel.form>
    </x-modal>
@endcan
@endsection
