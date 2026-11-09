@extends('layouts.dashboard')

@section('title', 'Data Pelanggan')

@section('content')
<div class="space-y-6">
    <x-panel.header title="Data Pelanggan" subtitle="Pelanggan website dan pelanggan walk-in outlet dalam satu daftar. Nomor HP unik per pelanggan.">
        @can('pelanggan.merge')
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'gabung-pelanggan')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-bold text-xs">
                Gabungkan Pelanggan Ganda
            </button>
        @endcan
        @can('pelanggan.create')
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'tambah-pelanggan')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs">
                <x-icon name="plus" class="w-4 h-4" />
                <span>Tambah Pelanggan</span>
            </button>
        @endcan
    </x-panel.header>

    <x-card padding="p-4">
        <form method="GET" action="{{ route('panel.pelanggan.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <input type="search" name="search" value="{{ $search }}" placeholder="Cari nama, nomor HP, atau email…" class="sm:col-span-7 rounded-xl border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500" />
            <select name="status" class="sm:col-span-3 rounded-xl border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500">
                @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'semua' => 'Semua status'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="sm:col-span-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs">Cari</button>
        </form>
    </x-card>

    @if ($customers->isEmpty())
        <x-card padding="p-0">
            <x-empty-state
                :title="$search ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan'"
                :description="$search ? 'Coba kata kunci lain atau tambahkan pelanggan baru.' : 'Tambahkan pelanggan walk-in saat melayani di outlet.'"
            />
        </x-card>
    @else
        <x-table :headers="['Nama', 'Nomor HP', 'Asal', 'Transaksi', 'Status', '']">
            @foreach ($customers as $customer)
                <tr class="hover:bg-slate-50/60" data-customer="{{ $customer->id }}">
                    <td class="px-5 py-3">
                        <p class="font-bold text-slate-900">{{ $customer->name }}</p>
                        @unless ($customer->isWalkIn())
                            <p class="text-[11px] text-slate-500">{{ $customer->email }}</p>
                        @endunless
                    </td>
                    <td class="px-5 py-3 font-mono text-xs">{{ $customer->phone_e164 ? \App\Support\PhoneNumber::display($customer->phone_e164) : ($customer->phone ?: '-') }}</td>
                    <td class="px-5 py-3 text-xs">{{ $customer->isWalkIn() ? 'Outlet' : 'Website' }}</td>
                    <td class="px-5 py-3 text-xs">{{ $customer->checkouts_count + $customer->orders_count }}</td>
                    <td class="px-5 py-3">
                        <x-badge :variant="$customer->is_active ? 'success' : 'danger'" size="sm">{{ $customer->is_active ? 'Aktif' : ($customer->merged_into_id ? 'Digabung' : 'Nonaktif') }}</x-badge>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('panel.pelanggan.show', $customer) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">Detail</a>
                    </td>
                </tr>
            @endforeach
        </x-table>

        {{ $customers->links() }}
    @endif
</div>

@can('pelanggan.create')
    <x-modal name="tambah-pelanggan" title="Tambah Pelanggan Walk-in" maxWidth="md">
        <x-panel.form :action="route('panel.pelanggan.store')" class="space-y-4">
            <input type="hidden" name="_modal" value="tambah-pelanggan" />
            <x-panel.field label="Nama" name="name" required maxlength="100" />
            <x-panel.field label="Nomor HP" name="phone" type="tel" required hint="Disimpan dalam format +62 dan harus unik." placeholder="0812xxxxxxxx" />
            <label class="block text-xs">
                <span class="font-bold text-slate-700">Catatan</span>
                <textarea name="notes" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500">{{ old('notes') }}</textarea>
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'tambah-pelanggan')" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Batal</button>
                <x-panel.submit>Simpan Pelanggan</x-panel.submit>
            </div>
        </x-panel.form>
    </x-modal>
@endcan

@can('pelanggan.merge')
    <x-modal name="gabung-pelanggan" title="Gabungkan Pelanggan Ganda" maxWidth="md">
        <x-panel.form :action="route('panel.pelanggan.merge')" class="space-y-4" confirm="Semua transaksi pelanggan ganda akan dipindah ke pelanggan utama dan akun ganda dinonaktifkan. Lanjutkan?">
            <input type="hidden" name="_modal" value="gabung-pelanggan" />
            <p class="text-xs text-slate-600">Gunakan ID pelanggan dari halaman detail. Semua transaksi akun ganda dipindah ke akun utama, lalu akun ganda dinonaktifkan. Tercatat di audit log.</p>
            <x-panel.field label="ID pelanggan utama (dipertahankan)" name="primary_id" type="number" required min="1" />
            <x-panel.field label="ID pelanggan ganda (dinonaktifkan)" name="duplicate_id" type="number" required min="1" />
            <x-panel.field label="Alasan" name="reason" required minlength="10" hint="Minimal 10 karakter." />
            <div class="flex justify-end">
                <x-panel.submit variant="danger">Gabungkan</x-panel.submit>
            </div>
        </x-panel.form>
    </x-modal>
@endcan
@endsection
