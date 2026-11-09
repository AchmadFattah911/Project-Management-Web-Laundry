@extends('layouts.dashboard')

@section('title', 'Transaksi Outlet Baru')

@section('content')
@php
    $serviceOptions = $services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'price' => \App\Support\Rupiah::parse($s->price)])->values();
    $oldItems = old('items', [['service_id' => '', 'qty' => 1, 'item_type' => '', 'notes' => '']]);
@endphp
<div class="max-w-4xl space-y-6"
     x-data="outletTransactionForm({
        services: @js($serviceOptions),
        items: @js(array_values($oldItems)),
        searchUrl: @js(route('panel.pelanggan.search')),
        customerId: @js(old('customer_id')),
        customerLabel: @js(old('customer_label')),
        mode: @js(old('customer_id') || ! old('new_customer_name') ? 'existing' : 'new'),
        timing: @js(old('payment_timing', 'now')),
        method: @js(old('payment_method', 'cash')),
        cash: @js(old('cash_received')),
     })">
    <div>
        <a href="{{ route('panel.transaksi.index') }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">&larr; Transaksi</a>
    </div>
    <x-panel.header title="Transaksi Outlet Baru" subtitle="Harga dihitung server dari tarif layanan. Transaksi tunai bayar sekarang wajib punya shift kas yang buka." />

    @if ($errors->any())
        <x-alert type="error" :message="$errors->first()" />
    @endif

    <x-panel.form :action="route('panel.transaksi.store')" class="space-y-6">
        {{-- 1. Pelanggan --}}
        <x-card title="1. Pelanggan">
            <div class="flex gap-2 mb-4" role="tablist">
                <button type="button" x-on:click="mode = 'existing'" :class="mode === 'existing' ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-full text-xs font-bold">Pilih pelanggan</button>
                <button type="button" x-on:click="mode = 'new'; customerId = null" :class="mode === 'new' ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-full text-xs font-bold">Tambah cepat</button>
            </div>

            <div x-show="mode === 'existing'" class="space-y-2">
                <input type="hidden" name="customer_id" :value="mode === 'existing' ? customerId : ''" />
                <input type="hidden" name="customer_label" :value="customerLabel" />
                <input type="search" x-model="query" x-on:input.debounce.300ms="search()" placeholder="Cari nama atau nomor HP (min. 2 huruf)" class="w-full rounded-xl border-slate-200 text-sm" aria-label="Cari pelanggan" />
                <p x-show="loading" class="text-[11px] text-slate-500">Mencari…</p>
                <ul x-show="results.length" class="border border-slate-200 rounded-xl divide-y divide-slate-100 max-h-56 overflow-y-auto">
                    <template x-for="c in results" :key="c.id">
                        <li><button type="button" x-on:click="pick(c)" class="w-full text-left px-3 py-2 text-xs hover:bg-sky-50"><span class="font-bold" x-text="c.name"></span> <span class="text-slate-500" x-text="c.phone"></span></button></li>
                    </template>
                </ul>
                <p x-show="customerId" class="text-xs text-emerald-700 font-bold">Dipilih: <span x-text="customerLabel"></span></p>
            </div>

            <div x-show="mode === 'new'" x-cloak class="grid sm:grid-cols-2 gap-4">
                <label class="block text-xs"><span class="font-bold text-slate-700">Nama *</span>
                    <input type="text" name="new_customer_name" value="{{ old('new_customer_name') }}" :disabled="mode !== 'new'" maxlength="100" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                <label class="block text-xs"><span class="font-bold text-slate-700">Nomor HP *</span>
                    <input type="tel" name="new_customer_phone" value="{{ old('new_customer_phone') }}" :disabled="mode !== 'new'" placeholder="0812xxxxxxxx" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
            </div>
        </x-card>

        {{-- 2. Layanan --}}
        <x-card title="2. Layanan">
            @if ($services->isEmpty())
                <x-empty-state title="Tidak ada layanan tersedia" description="Aktifkan layanan di menu Layanan Laundry." />
            @else
                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid grid-cols-12 gap-2 items-end">
                            <label class="col-span-12 sm:col-span-5 text-xs"><span class="font-bold text-slate-700">Layanan</span>
                                <select :name="`items[${index}][service_id]`" x-model="item.service_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                    <option value="">Pilih layanan</option>
                                    <template x-for="s in services" :key="s.id"><option :value="s.id" x-text="`${s.name} (${rupiah(s.price)}/mesin)`" :selected="String(s.id) === String(item.service_id)"></option></template>
                                </select></label>
                            <label class="col-span-4 sm:col-span-2 text-xs"><span class="font-bold text-slate-700">Mesin</span>
                                <input type="number" min="1" max="{{ config('laundry.max_machine_qty', 20) }}" :name="`items[${index}][qty]`" x-model.number="item.qty" required class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                            <label class="col-span-8 sm:col-span-3 text-xs"><span class="font-bold text-slate-700">Catatan</span>
                                <input type="text" maxlength="500" :name="`items[${index}][notes]`" x-model="item.notes" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                            <div class="col-span-12 sm:col-span-2 flex items-center justify-between sm:justify-end gap-2 pb-2">
                                <span class="text-xs font-bold text-slate-700" x-text="rupiah(lineTotal(item))"></span>
                                <button type="button" x-show="items.length > 1" x-on:click="items.splice(index, 1)" class="text-rose-600 text-xs font-bold" aria-label="Hapus baris">Hapus</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" x-on:click="items.push({ service_id: '', qty: 1, notes: '' })" x-show="items.length < {{ config('laundry.cart_max_lines', 10) }}" class="text-xs font-bold text-sky-600">+ Tambah layanan</button>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-sm">
                        <span class="text-slate-500">Perkiraan total <span class="text-[11px]">(final dihitung server)</span></span>
                        <span class="font-extrabold text-slate-900" x-text="rupiah(total())"></span>
                    </div>
                </div>
            @endif
        </x-card>

        {{-- 3. Pembayaran --}}
        <x-card title="3. Pembayaran">
            <div class="grid sm:grid-cols-2 gap-4">
                <fieldset class="text-xs space-y-2"><legend class="font-bold text-slate-700 mb-1">Waktu bayar</legend>
                    <label class="flex items-center gap-2"><input type="radio" name="payment_timing" value="now" x-model="timing" /> Bayar sekarang</label>
                    <label class="flex items-center gap-2"><input type="radio" name="payment_timing" value="on_pickup" x-model="timing" /> Bayar saat ambil</label>
                </fieldset>
                <label class="text-xs"><span class="font-bold text-slate-700">Metode</span>
                    <select name="payment_method" x-model="method" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        @foreach (config('finance.outlet_methods') as $key => $m)
                            <option value="{{ $key }}">{{ $m['label'] }}</option>
                        @endforeach
                    </select></label>
                <label class="text-xs" x-show="timing === 'now' && method === 'cash'"><span class="font-bold text-slate-700">Uang diterima *</span>
                    <input type="number" min="1" name="cash_received" x-model.number="cash" :disabled="!(timing === 'now' && method === 'cash')" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                    <span class="mt-1 block text-[11px]" :class="cash >= total() ? 'text-emerald-700' : 'text-rose-600'" x-text="cash ? (cash >= total() ? 'Kembalian ' + rupiah(cash - total()) : 'Uang kurang') : ''"></span></label>
                <label class="text-xs" x-show="timing === 'now' && method !== 'cash'" x-cloak><span class="font-bold text-slate-700">Nomor bukti *</span>
                    <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="60" :disabled="!(timing === 'now' && method !== 'cash')" class="mt-1 w-full rounded-xl border-slate-200 text-sm uppercase" /></label>
                <label class="sm:col-span-2 text-xs"><span class="font-bold text-slate-700">Catatan transaksi</span>
                    <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea></label>
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('panel.transaksi.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Batal</a>
            <x-panel.submit>Simpan Transaksi</x-panel.submit>
        </div>
    </x-panel.form>
</div>

@push('scripts')
<script>
    function outletTransactionForm(init) {
        return {
            ...init,
            query: '',
            results: [],
            loading: false,
            rupiah(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); },
            price(id) { return (this.services.find(s => String(s.id) === String(id)) || { price: 0 }).price; },
            lineTotal(item) { return this.price(item.service_id) * (parseInt(item.qty) || 0); },
            total() { return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0); },
            async search() {
                if (this.query.trim().length < 2) { this.results = []; return; }
                this.loading = true;
                try {
                    const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } });
                    this.results = res.ok ? await res.json() : [];
                } finally { this.loading = false; }
            },
            pick(c) { this.customerId = c.id; this.customerLabel = c.name + ' (' + (c.phone || '-') + ')'; this.results = []; this.query = ''; },
        };
    }
</script>
@endpush
@endsection
