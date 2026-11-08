@php
    $stepLabels = ['cuci' => 'Cuci', 'kering' => 'Kering', 'lipat' => 'Lipat'];

    $serviceData = $services->map(fn ($s) => [
        'id'          => $s->id,
        'name'        => $s->name,
        'price'       => (float) $s->price,
        'unit'        => $s->unit ?? 'Mesin',
    ])->values();

    // Input form dipertahankan saat validasi gagal; kartu dari tombol "Pesan Sekarang" langsung tercentang.
    // Key items[] = id layanan, sehingga error "items.{id}.qty" otomatis tampil di kartu yang benar.
    $oldItems = old('items', []);
    $initial = $services->mapWithKeys(function ($s) use ($oldItems, $preselectedId) {
        $old = $oldItems[$s->id] ?? null;

        return [$s->id => [
            'checked'   => $old !== null || (empty($oldItems) && $preselectedId === $s->id),
            'qty'       => max(1, (int) ($old['qty'] ?? 1)),
            'item_type' => $old['item_type'] ?? 'pakaian',
            'notes'     => $old['notes'] ?? '',
            'weight'    => '',
        ]];
    });
@endphp

<form
    action="{{ route('pelanggan.cart.store') }}"
    method="POST"
    x-data="orderForm({
        services: {{ Js::from($serviceData) }},
        itemTypes: {{ Js::from($itemTypes) }},
        maxQty: {{ (int) $maxQty }},
        sel: {{ Js::from($initial) }},
        cartCount: {{ $cart['items']->count() }},
        cartUrl: {{ Js::from(route('pelanggan.cart.index')) }},
    })"
    x-on:submit="onSubmit($event)"
    class="rounded-2xl border border-slate-200 bg-white shadow-xs"
>
    @csrf
    <input type="hidden" name="lanjut" :value="proceed ? 1 : 0" />

    <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-slate-100">
        <h2 class="text-base font-extrabold text-slate-900">1. Pilih Layanan Drop-Off</h2>
        <p class="text-xs text-slate-500 mt-0.5">Centang satu atau beberapa layanan. Ikon keranjang di bawah menyimpan pilihan dan Anda bisa memilih lagi; tombol Lanjutkan menyimpan pilihan lalu membuka keranjang.</p>
    </div>

    @error('items')
        <div class="mx-5 sm:mx-6 mt-4"><x-alert type="error" :message="$message" :dismissible="false" /></div>
    @enderror

    @if ($services->isEmpty())
        <div class="p-6">
            <x-empty-state title="Belum Ada Layanan" description="Saat ini belum ada layanan drop-off yang dapat dipesan online." />
        </div>
    @else
        <div class="p-5 sm:p-6 space-y-3">
            @foreach ($services as $service)
                @php
                    $id = $service->id;
                    $cardErrors = collect(['service_id', 'qty', 'item_type', 'notes'])
                        ->map(fn ($f) => $errors->first("items.{$id}.{$f}"))
                        ->filter();
                @endphp
                <div
                    :class="sel[{{ $id }}].checked ? 'border-sky-500 ring-2 ring-sky-100 bg-sky-50/30' : 'border-slate-200 hover:border-slate-300'"
                    @class([
                        'rounded-xl border transition-colors',
                        'outline-2 outline-offset-2 outline-rose-400' => $cardErrors->isNotEmpty(),
                    ])
                >
                    <!-- Header kartu: centang layanan -->
                    <label class="flex items-start gap-3 p-4 cursor-pointer">
                        <input
                            type="checkbox"
                            x-model="sel[{{ $id }}].checked"
                            class="mt-1 w-5 h-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                            aria-describedby="srv-{{ $id }}-desc"
                        />
                        <span class="flex-1 min-w-0">
                            <span class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <span class="text-sm font-extrabold text-slate-900">{{ $service->name }}</span>
                                <span class="text-sm font-extrabold text-sky-700">
                                    Rp {{ number_format($service->price, 0, ',', '.') }}
                                    <span class="text-[11px] font-semibold text-slate-500">/ {{ $service->unit ?? 'Mesin' }}</span>
                                </span>
                            </span>
                            <span id="srv-{{ $id }}-desc" class="block text-xs text-slate-500 mt-0.5">{{ $service->description }}</span>
                            <span class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-600">
                                    <x-icon name="clock" class="w-3.5 h-3.5" /> {{ $service->estimate }}
                                </span>
                                <span class="text-slate-300">|</span>
                                <span class="font-semibold text-slate-600">Proses:</span>
                                @foreach ($service->processSteps() as $proc)
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold">{{ $stepLabels[$proc] ?? ucfirst($proc) }}</span>
                                    @if (! $loop->last) <span class="text-slate-400">→</span> @endif
                                @endforeach
                            </span>
                        </span>
                    </label>

                    <!-- Detail layanan (hanya terkirim jika dicentang: input disabled tidak ikut dikirim) -->
                    <div x-show="sel[{{ $id }}].checked" x-collapse @if (! $initial[$id]['checked']) x-cloak @endif>
                        <div class="px-4 pb-4 pt-1 grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 mt-1">
                            <input type="hidden" name="items[{{ $id }}][service_id]" value="{{ $id }}" :disabled="! included({{ $id }})" />

                            <!-- Jenis cucian -->
                            <div class="pt-3">
                                <label for="srv-{{ $id }}-type" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Jenis Cucian
                                    <x-help-tip text="Menentukan batas muatan per mesin: pakaian 6 KG, sprei/selimut tipis 3 KG, bedcover 1 pcs. Campuran memakai batas aman 3 KG." />
                                </label>
                                <select
                                    id="srv-{{ $id }}-type"
                                    name="items[{{ $id }}][item_type]"
                                    x-model="sel[{{ $id }}].item_type"
                                    :disabled="! included({{ $id }})"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                >
                                    @foreach ($itemTypes as $key => $type)
                                        <option value="{{ $key }}">{{ $type['label'] }} (maks {{ $type['capacity'] }} {{ $type['unit'] }}/mesin)</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Jumlah mesin -->
                            <div class="pt-3">
                                <label for="srv-{{ $id }}-qty" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Jumlah Mesin
                                    <x-help-tip text="Satu mesin = satu kali proses. Belum yakin? Gunakan bantuan hitung (opsional) di bawah." />
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" x-on:click="dec({{ $id }})" :disabled="! sel[{{ $id }}].checked" class="w-9 h-9 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 flex items-center justify-center" aria-label="Kurangi jumlah mesin {{ $service->name }}">
                                        <x-icon name="minus" class="w-4 h-4" />
                                    </button>
                                    <input
                                        id="srv-{{ $id }}-qty"
                                        type="number"
                                        name="items[{{ $id }}][qty]"
                                        x-model.number="sel[{{ $id }}].qty"
                                        x-on:blur="clamp({{ $id }})"
                                        :disabled="! included({{ $id }})"
                                        min="1"
                                        max="{{ $maxQty }}"
                                        class="w-16 text-center rounded-lg border border-slate-300 py-1.5 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    />
                                    <button type="button" x-on:click="inc({{ $id }})" :disabled="! sel[{{ $id }}].checked" class="w-9 h-9 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 flex items-center justify-center" aria-label="Tambah jumlah mesin {{ $service->name }}">
                                        <x-icon name="plus" class="w-4 h-4" />
                                    </button>
                                    <span class="ml-auto text-xs font-bold text-slate-700" x-text="rupiah(price({{ $id }}) * (sel[{{ $id }}].qty || 0))"></span>
                                </div>
                            </div>

                            <!-- Pembantu kapasitas (OPSIONAL): tidak dikirim ke server, hanya membantu memilih jumlah mesin -->
                            <div class="sm:col-span-2" x-data="{ helper: false }">
                                <button
                                    type="button"
                                    x-on:click="helper = ! helper"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800"
                                    :aria-expanded="helper.toString()"
                                >
                                    <x-icon name="sparkles" class="w-3.5 h-3.5" />
                                    <span x-text="helper ? 'Tutup bantuan hitung mesin' : 'Bingung jumlah mesin? Hitung dari perkiraan muatan (opsional)'">Bingung jumlah mesin? Hitung dari perkiraan muatan (opsional)</span>
                                </button>
                            <div x-show="helper" x-collapse x-cloak class="mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <label for="srv-{{ $id }}-weight" class="font-bold text-amber-900">Perkiraan muatan <span class="font-medium">(opsional)</span></label>
                                    <input
                                        id="srv-{{ $id }}-weight"
                                        type="number"
                                        min="0"
                                        step="0.5"
                                        x-model="sel[{{ $id }}].weight"
                                        placeholder="0"
                                        class="w-20 rounded-md border border-amber-300 px-2 py-1 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-100"
                                    />
                                    <span class="text-amber-900" x-text="itemTypes[sel[{{ $id }}].item_type]?.unit ?? 'KG'"></span>
                                    <template x-if="suggestion({{ $id }})">
                                        <span class="flex items-center gap-2">
                                            <span class="text-amber-900">→ saran <strong x-text="suggestion({{ $id }})"></strong> mesin</span>
                                            <button type="button" x-on:click="sel[{{ $id }}].qty = suggestion({{ $id }})" class="px-2 py-0.5 rounded-md bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold">Pakai saran</button>
                                        </span>
                                    </template>
                                </div>
                                <p class="text-[11px] text-amber-800 mt-1">Boleh dikosongkan. Hanya untuk membantu; petugas akan mengecek ulang muatan saat cucian diterima di outlet.</p>
                            </div>
                            </div>

                            <!-- Catatan per layanan -->
                            <div class="sm:col-span-2">
                                <label for="srv-{{ $id }}-notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">Catatan untuk layanan ini (opsional)</label>
                                <input
                                    id="srv-{{ $id }}-notes"
                                    type="text"
                                    name="items[{{ $id }}][notes]"
                                    x-model="sel[{{ $id }}].notes"
                                    :disabled="! included({{ $id }})"
                                    maxlength="500"
                                    placeholder="Contoh: pisahkan pakaian putih"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            @foreach ($cardErrors as $message)
                                <p class="sm:col-span-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @endforeach

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Bar bawah: ikon keranjang di kiri, tombol Lanjutkan di kanan. Muncul setelah ada layanan terpilih. -->
        <div
            x-show="selectedCount() > 0 || cartCount > 0"
            @if (! collect($initial)->contains('checked', true) && $cart['items']->isEmpty()) x-cloak @endif
            class="sticky bottom-0 z-20 rounded-b-2xl border-t border-slate-200 bg-white/95 backdrop-blur px-5 sm:px-6 py-4"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="cartAction($el.form)"
                    :disabled="submitting"
                    :aria-label="selectedCount() > 0 ? 'Masukkan layanan terpilih ke keranjang' : 'Buka keranjang'"
                    :title="selectedCount() > 0 ? 'Masukkan layanan terpilih ke keranjang' : 'Buka keranjang'"
                    class="relative w-11 h-11 shrink-0 rounded-lg border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-700 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <x-icon name="cart" class="w-5 h-5" />
                    <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-amber-400 text-slate-900 text-[10px] font-extrabold leading-5 text-center" aria-hidden="true"></span>
                </button>

                <div class="min-w-0 flex-1 text-xs">
                    <p class="font-bold text-slate-900 truncate" x-text="selectedCount() > 0 ? selectedCount() + ' layanan dipilih' : cartCount + ' layanan di keranjang'"></p>
                    <p class="text-slate-500 truncate" x-show="selectedCount() > 0">Estimasi <span class="font-extrabold text-sky-700" x-text="rupiah(selectedTotal())"></span></p>
                </div>

                <button
                    type="button"
                    x-on:click="continueOrder($el.form)"
                    :disabled="submitting"
                    class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    Lanjutkan
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </button>
            </div>
        </div>
    @endif
</form>

@push('scripts')
<script>
    // Alpine hanya untuk kenyamanan & estimasi; harga, batas, dan ketersediaan tetap divalidasi server.
    document.addEventListener('alpine:init', () => {
        Alpine.data('orderForm', (config) => ({
            ...config,
            submitting: false,
            proceed: false,
            // Hanya layanan yang dicentang yang inputnya aktif (input disabled tidak ikut dikirim)
            included(id) {
                return this.sel[id].checked;
            },
            selectedIds() {
                return Object.keys(this.sel).map(Number).filter((id) => this.sel[id].checked);
            },
            selectedCount() {
                return this.selectedIds().length;
            },
            selectedTotal() {
                return this.selectedIds().reduce((total, id) => total + this.price(id) * (Number(this.sel[id].qty) || 0), 0);
            },
            price(id) {
                return this.services.find((s) => s.id === id)?.price ?? 0;
            },
            inc(id) {
                this.sel[id].qty = Math.min(this.maxQty, (Number(this.sel[id].qty) || 0) + 1);
            },
            dec(id) {
                this.sel[id].qty = Math.max(1, (Number(this.sel[id].qty) || 1) - 1);
            },
            clamp(id) {
                const qty = Math.round(Number(this.sel[id].qty) || 1);
                this.sel[id].qty = Math.min(this.maxQty, Math.max(1, qty));
            },
            suggestion(id) {
                const weight = parseFloat(this.sel[id].weight);
                const capacity = this.itemTypes[this.sel[id].item_type]?.capacity;
                if (! weight || weight <= 0 || ! capacity) return null;
                return Math.min(this.maxQty, Math.max(1, Math.ceil(weight / capacity)));
            },
            rupiah(n) {
                return 'Rp ' + Math.round(n).toLocaleString('id-ID');
            },
            save(form, proceed) {
                if (this.submitting) return;
                this.selectedIds().forEach((id) => this.clamp(id));
                this.proceed = proceed;
                this.submitting = true;
                // Tunggu nilai input diperbarui Alpine, baru kirim form
                this.$nextTick(() => form.submit());
            },
            cartAction(form) {
                if (this.selectedCount() > 0) this.save(form, false);
                else window.location.href = this.cartUrl;
            },
            continueOrder(form) {
                if (this.selectedCount() > 0) this.save(form, true);
                else window.location.href = this.cartUrl;
            },
            onSubmit(event) {
                // Tekan Enter di dalam input tidak mengirim form; simpan lewat tombol di bar bawah
                event.preventDefault();
            },
        }));
    });
</script>
@endpush
