@php
    // Pengganti panel keranjang di halaman Pesan: ringkasan singkat + tombol ke langkah detail pemesanan.
    $compact = $compact ?? false;
    $canContinue = $cart['orderable']->isNotEmpty();
@endphp

@if ($compact)
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('pelanggan.cart.index') }}" class="text-xs">
            <span class="block font-bold text-slate-900">{{ $cart['items']->count() }} layanan di keranjang</span>
            <span class="text-slate-500">Total <span class="font-extrabold text-sky-700">Rp {{ number_format($cart['total'], 0, ',', '.') }}</span></span>
        </a>
        @if ($canContinue)
            <a href="{{ route('pelanggan.pesan.index', ['langkah' => 'detail']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold shadow-sm transition-colors">
                Lanjut ke Detail Pemesanan
                <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        @else
            <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-200 text-slate-500 text-sm font-bold cursor-not-allowed" aria-disabled="true">
                Lanjut ke Detail Pemesanan
                <x-icon name="arrow-right" class="w-4 h-4" />
            </span>
        @endif
    </div>
@else
    <section class="rounded-2xl border border-slate-200 bg-white shadow-xs p-5 space-y-4" aria-labelledby="lanjut-detail-title">
        <div>
            <h2 id="lanjut-detail-title" class="text-base font-extrabold text-slate-900">Siap memesan?</h2>
            <p class="text-xs text-slate-500 mt-0.5">Layanan yang dimasukkan lewat ikon keranjang akan dipesan di langkah berikutnya.</p>
        </div>

        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs text-slate-600">
                <span>Isi keranjang</span>
                <span class="font-bold text-slate-900">{{ $cart['items']->count() }} layanan · {{ $cart['machine_count'] }} mesin</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Total</span>
                <span class="text-lg font-extrabold text-sky-700">Rp {{ number_format($cart['total'], 0, ',', '.') }}</span>
            </div>
        </div>

        @if ($canContinue)
            <a href="{{ route('pelanggan.pesan.index', ['langkah' => 'detail']) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors">
                Lanjut ke Detail Pemesanan
                <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        @else
            <p class="text-center text-[11px] font-semibold text-slate-500">Masukkan minimal satu layanan ke keranjang untuk melanjutkan.</p>
        @endif

        @if ($cart['items']->isNotEmpty())
            <a href="{{ route('pelanggan.cart.index') }}" class="flex items-center justify-center gap-1.5 text-xs font-bold text-sky-700 hover:text-sky-800">
                <x-icon name="cart" class="w-4 h-4" /> Lihat / ubah keranjang
            </a>
        @endif
    </section>
@endif
