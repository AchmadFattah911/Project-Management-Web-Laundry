@php
    $highlight = session('cart_highlight', []);
    $readonly = $readonly ?? false;
    $prices = $cart['orderable']->mapWithKeys(fn ($item) => [$item->id => $item->subtotal()]);
@endphp

<section
    class="rounded-2xl border border-slate-200 bg-white shadow-xs"
    aria-labelledby="keranjang-title"
    @unless ($readonly)
        x-data="cartSelection({
            prices: {{ Js::from($prices) }},
            fresh: {{ Js::from(array_map('strval', $highlight)) }},
        })"
    @endunless
>
    <div class="px-5 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between">
        <h2 id="keranjang-title" class="flex items-center gap-2 text-base font-extrabold text-slate-900">
            <x-icon name="cart" class="w-5 h-5 text-sky-600" />
            Keranjang
        </h2>
        <span class="text-xs font-bold text-slate-500">{{ $cart['items']->count() }} layanan</span>
    </div>

    @error('qty')
        <div class="mx-5 mt-4"><x-alert type="error" :message="$message" :dismissible="false" /></div>
    @enderror

    @if ($cart['items']->isEmpty())
        <div class="px-5 py-10 text-center">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                <x-icon name="cart" class="w-6 h-6" />
            </div>
            <p class="text-sm font-bold text-slate-700">Keranjang masih kosong</p>
            <p class="text-xs text-slate-500 mt-1">Centang layanan di halaman Pesan, lalu klik ikon keranjang di bar bawah.</p>
            <a href="{{ route('pelanggan.pesan.index') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-colors">
                Pilih Layanan
            </a>
        </div>
    @else
        @if (! $readonly && $prices->isNotEmpty())
            <label class="px-5 py-3 border-b border-slate-100 flex items-center gap-3 text-xs font-bold text-slate-700 cursor-pointer">
                <input type="checkbox" :checked="allSelected()" x-on:change="toggleAll($event.target.checked)" class="w-5 h-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                Pilih semua
            </label>
        @endif

        <ul class="divide-y divide-slate-100">
            @foreach ($cart['items'] as $item)
                @php $isNew = in_array($item->id, $highlight, true); $orderable = $item->isOrderable(); @endphp
                <li @class(['px-5 py-4', 'bg-emerald-50/70' => $isNew, 'opacity-60' => ! $orderable])>
                    <div class="flex items-start justify-between gap-3">
                        @if (! $readonly && $orderable)
                            <input
                                type="checkbox"
                                name="cart_items[]"
                                value="{{ $item->id }}"
                                form="lanjut-bayar"
                                x-model="selected"
                                class="mt-0.5 w-5 h-5 shrink-0 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                aria-label="Pilih {{ $item->service?->name }} untuk dibayar"
                            />
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 leading-snug">
                                {{ $item->service?->name ?? 'Layanan dihapus' }}
                                @if ($isNew)
                                    <span class="ml-1 align-middle px-1.5 py-0.5 rounded bg-emerald-500 text-white text-[10px] font-extrabold">BARU</span>
                                @endif
                            </p>
                            <p class="text-[11px] text-slate-500">{{ $item->itemTypeLabel() }} · Rp {{ number_format($item->service?->price ?? 0, 0, ',', '.') }}/mesin</p>
                            @if ($item->notes)
                                <p class="text-[11px] text-slate-600 italic mt-0.5">"{{ $item->notes }}"</p>
                            @endif
                            @unless ($orderable)
                                <p class="text-[11px] font-bold text-rose-600 mt-1">Layanan sedang tidak tersedia, tidak ikut dipesan. Hapus dari keranjang.</p>
                            @endunless
                        </div>
                        <p class="text-sm font-extrabold text-slate-900 shrink-0">Rp {{ number_format($item->subtotal(), 0, ',', '.') }}</p>
                    </div>

                    <div class="mt-2 flex items-center justify-between">
                        @if ($readonly || ! $orderable)
                            <span class="text-xs font-semibold text-slate-600">{{ $item->qty }} × mesin</span>
                        @else
                            {{-- Tombol -/+ langsung mengirim jumlah baru, tanpa perlu JavaScript --}}
                            <form action="{{ route('pelanggan.cart.update', $item->id) }}" method="POST" class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <button type="submit" name="qty" value="{{ $item->qty - 1 }}" @disabled($item->qty <= 1) class="w-7 h-7 rounded-md border border-slate-300 flex items-center justify-center hover:bg-slate-50 disabled:opacity-40" aria-label="Kurangi">
                                    <x-icon name="minus" class="w-3.5 h-3.5" />
                                </button>
                                <span class="w-8 text-center text-sm font-bold">{{ $item->qty }}</span>
                                <button type="submit" name="qty" value="{{ $item->qty + 1 }}" @disabled($item->qty >= $maxQty) class="w-7 h-7 rounded-md border border-slate-300 flex items-center justify-center hover:bg-slate-50 disabled:opacity-40" aria-label="Tambah">
                                    <x-icon name="plus" class="w-3.5 h-3.5" />
                                </button>
                                <span class="text-[11px] text-slate-500 ml-1">mesin</span>
                            </form>
                        @endif

                        @unless ($readonly)
                            <form action="{{ route('pelanggan.cart.destroy', $item->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 hover:text-rose-700">
                                    <x-icon name="trash" class="w-3.5 h-3.5" /> Hapus
                                </button>
                            </form>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($readonly)
            <div class="px-5 py-4 border-t border-slate-200 bg-slate-50/70 rounded-b-2xl space-y-3">
                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>Total mesin</span>
                    <span class="font-bold text-slate-900">{{ $cart['machine_count'] }} mesin</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Total</span>
                    <span class="text-lg font-extrabold text-sky-700">Rp {{ number_format($cart['total'], 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('pelanggan.cart.index') }}" class="block text-center text-xs font-bold text-sky-700 hover:text-sky-800">
                    Ubah isi keranjang
                </a>
            </div>
        @elseif ($prices->isEmpty())
            <p class="px-5 py-4 border-t border-slate-200 text-center text-[11px] font-semibold text-rose-600">Tidak ada layanan yang bisa dipesan di keranjang.</p>
        @else
            {{-- Checkbox item terhubung ke form ini lewat atribut form="lanjut-bayar" --}}
            <form
                id="lanjut-bayar"
                action="{{ route('pelanggan.pesan.index') }}"
                method="GET"
                class="sticky bottom-0 z-20 px-5 py-4 border-t border-slate-200 bg-white/95 backdrop-blur rounded-b-2xl"
            >
                <input type="hidden" name="langkah" value="detail" />

                <p x-show="selected.length === 0" class="text-center text-xs font-semibold text-slate-500">Centang layanan yang ingin dibayar sekarang.</p>

                <div x-show="selected.length > 0" x-cloak class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs">
                        <p class="font-bold text-slate-900">Ingin melanjutkan pembayaran?</p>
                        <p class="text-slate-500"><span x-text="selected.length"></span> layanan · <span class="font-extrabold text-sky-700" x-text="rupiah(selectedTotal())"></span></p>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold shadow-sm transition-colors">
                        Lanjutkan
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>
            </form>
        @endif
    @endif
</section>

@unless ($readonly)
    @push('scripts')
    <script>
        // Pilihan hanya kenyamanan tampilan; item, harga, dan pemiliknya tetap dicek ulang di server.
        document.addEventListener('alpine:init', () => {
            Alpine.data('cartSelection', ({ prices, fresh }) => ({
                prices,
                selected: [],
                init() {
                    let saved = [];
                    try { saved = JSON.parse(sessionStorage.getItem('lc_cart_selected')) ?? []; } catch (e) {}
                    // Ubah jumlah mesin memuat ulang halaman: pilihan dipertahankan, layanan yang baru masuk ikut tercentang
                    this.selected = [...new Set([...saved, ...fresh])].filter((id) => id in this.prices);
                    this.$watch('selected', (value) => {
                        try { sessionStorage.setItem('lc_cart_selected', JSON.stringify(value)); } catch (e) {}
                    });
                },
                allSelected() {
                    return this.selected.length === Object.keys(this.prices).length;
                },
                toggleAll(checked) {
                    this.selected = checked ? Object.keys(this.prices) : [];
                },
                selectedTotal() {
                    return this.selected.reduce((total, id) => total + (this.prices[id] ?? 0), 0);
                },
                rupiah(n) {
                    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
                },
            }));
        });
    </script>
    @endpush
@endunless
