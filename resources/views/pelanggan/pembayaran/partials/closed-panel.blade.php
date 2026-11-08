{{--
    Transaksi berhenti tanpa dibayar (gagal / kadaluarsa / dibatalkan): tidak bisa dibayar lagi, bisa "Pesan Lagi".
    Variabel: $checkout
--}}
@php
    $text = match ($checkout->status) {
        \App\Models\Checkout::STATUS_CANCELLED => ['Dibatalkan', 'Transaksi ini sudah dibatalkan. Tidak ada dana yang ditagih.'],
        \App\Models\Checkout::STATUS_EXPIRED   => ['Kedaluwarsa', 'Batas waktu pembayaran transaksi ini sudah habis.'],
        default                                => ['Gagal', 'Pembayaran transaksi ini gagal atau ditolak.'],
    };
@endphp

<div class="mt-4 space-y-4">
    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
        <x-badge variant="danger" size="sm">{{ $text[0] }}</x-badge>
        <p class="mt-2 text-xs text-slate-700">{{ $text[1] }}</p>
    </div>

    <form action="{{ route('pelanggan.pembayaran.reorder', $checkout) }}" method="POST" x-data="{ sending: false }" x-on:submit="if (sending) { $event.preventDefault(); return; } sending = true">
        @csrf
        <button type="submit" :disabled="sending" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shadow-sm disabled:opacity-60">
            Pesan Lagi
        </button>
    </form>
    <p class="text-[11px] text-slate-500 text-center">Layanan dimasukkan ke keranjang dengan harga terbaru.</p>
</div>
