@php
    $highlight = session('cart_highlight', []);
    $readonly = $readonly ?? false;
@endphp

<section class="rounded-2xl border border-slate-200 bg-white shadow-xs" aria-labelledby="keranjang-title">
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
            <p class="text-xs text-slate-500 mt-1">Centang layanan di sebelah kiri, lalu klik "Tambah ke Keranjang".</p>
        </div>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($cart['items'] as $item)
                @php $isNew = in_array($item->id, $highlight, true); $orderable = $item->isOrderable(); @endphp
                <li @class(['px-5 py-4', 'bg-emerald-50/70' => $isNew, 'opacity-60' => ! $orderable])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
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

        <div class="px-5 py-4 border-t border-slate-200 bg-slate-50/70 rounded-b-2xl space-y-3">
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span>Total mesin</span>
                <span class="font-bold text-slate-900">{{ $cart['machine_count'] }} mesin</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Total</span>
                <span class="text-lg font-extrabold text-sky-700">Rp {{ number_format($cart['total'], 0, ',', '.') }}</span>
            </div>

            @if ($readonly)
                <a href="{{ route('pelanggan.pesan.index') }}" class="block text-center text-xs font-bold text-sky-700 hover:text-sky-800">
                    Ubah isi keranjang
                </a>
            @elseif ($cart['orderable']->isNotEmpty())
                <a href="{{ route('pelanggan.pesan.index', ['langkah' => 'detail']) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors">
                    Lanjut ke Detail Pesanan
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            @else
                <p class="text-center text-[11px] font-semibold text-rose-600">Tidak ada layanan yang bisa dipesan di keranjang.</p>
            @endif
        </div>
    @endif
</section>
