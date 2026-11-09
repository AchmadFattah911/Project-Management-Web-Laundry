{{--
    Satu kartu riwayat = 1 transaksi keranjang (Checkout) atau 1 pesanan lama / walk-in (Order).
    Transaksi yang belum dibayar tidak tampil di riwayat (lihat CustomerOrderHistory).
    Variabel: $card (Checkout|Order)
--}}
@php
    $isCheckout = $card instanceof \App\Models\Checkout;
    $group = \App\Services\CustomerOrderHistory::tabFor($card);
    $code = $isCheckout ? $card->code : $card->order_code;
    $items = $isCheckout ? $card->orders : collect([$card]);
    $total = $isCheckout ? $card->total_amount : $card->total_price;
    $detailUrl = $isCheckout ? route('pelanggan.transaksi.show', $card) : route('pelanggan.orders.show', $card);

    $badge = match ($group) {
        'diproses'    => ['primary', 'Diproses'],
        'selesai'     => ['success', 'Selesai'],
        default       => ['danger', $isCheckout && $card->isCancelledAfterPayment() ? 'Dibatalkan · Refund' : match ($card->status) {
            \App\Models\Checkout::STATUS_EXPIRED => 'Kedaluwarsa',
            \App\Models\Checkout::STATUS_FAILED  => 'Gagal',
            default                              => 'Dibatalkan',
        }],
    };

    $timezone = config('laundry.report_timezone', 'Asia/Jakarta');
@endphp

<article class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3" data-card="{{ $code }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-sm font-bold text-slate-900 truncate">{{ $code }}</p>
            <p class="text-[11px] text-slate-500">
                {{ $card->created_at->timezone($timezone)->translatedFormat('d M Y, H:i') }}
                @unless ($isCheckout) &middot; Pesanan di outlet @endunless
            </p>
        </div>
        <x-badge :variant="$badge[0]" size="sm" class="shrink-0">{{ $badge[1] }}</x-badge>
    </div>

    <ul class="space-y-1">
        @foreach ($items->take(3) as $item)
            <li class="flex items-center justify-between gap-2 text-xs">
                <span class="text-slate-700 truncate">{{ $item->serviceName() }} <span class="text-slate-400">&times; {{ $item->machine_qty }}</span></span>
                @if ($group === 'diproses')
                    <x-badge :status="$item->status" size="sm" :dot="false" class="shrink-0" />
                @endif
            </li>
        @endforeach
        @if ($items->count() > 3)
            <li class="text-[11px] text-slate-500">+{{ $items->count() - 3 }} layanan lainnya</li>
        @endif
    </ul>

    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
        <span class="text-[11px] text-slate-500">Total</span>
        <span class="text-sm font-extrabold text-slate-900">Rp {{ number_format((float) $total, 0, ',', '.') }}</span>
    </div>

    @if ($isCheckout && in_array($card->status, \App\Models\Checkout::CLOSED_UNPAID, true))
        <form action="{{ route('pelanggan.pembayaran.reorder', $card) }}" method="POST" x-data="{ sending: false }" x-on:submit="if (sending) { $event.preventDefault(); return; } sending = true">
            @csrf
            <button type="submit" :disabled="sending" class="w-full px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold disabled:opacity-60">
                Pesan Lagi
            </button>
        </form>
    @endif

    <a href="{{ $detailUrl }}" class="flex items-center justify-center gap-1 text-xs font-bold text-sky-600 hover:text-sky-700">
        <span>Lihat Detail / Nota</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </a>
</article>
