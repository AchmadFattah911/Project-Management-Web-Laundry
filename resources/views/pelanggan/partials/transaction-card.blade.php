{{--
    Satu kartu riwayat = 1 transaksi keranjang (Checkout) atau 1 pesanan lama / walk-in (Order).
    Variabel: $card (Checkout|Order), opsional $hideDetailLink (di halaman detail itu sendiri)
--}}
@php
    $isCheckout = $card instanceof \App\Models\Checkout;
    $group = \App\Services\CustomerOrderHistory::tabFor($card);
    $code = $isCheckout ? $card->code : $card->order_code;
    $items = $isCheckout ? $card->orders : collect([$card]);
    $total = $isCheckout ? $card->total_amount : $card->total_price;
    $detailUrl = $isCheckout ? route('pelanggan.transaksi.show', $card) : route('pelanggan.orders.show', $card);

    $badge = match ($group) {
        'belum_bayar' => ['warning', 'Belum Bayar'],
        'diproses'    => ['primary', 'Diproses'],
        'selesai'     => ['success', 'Selesai'],
        default       => ['danger', match ($card->status) {
            \App\Models\Checkout::STATUS_EXPIRED => 'Kedaluwarsa',
            \App\Models\Checkout::STATUS_FAILED  => 'Gagal',
            default                              => 'Dibatalkan',
        }],
    };

    $awaiting = $isCheckout && $card->isAwaitingPayment();
    $activePayment = $awaiting ? $card->payments->where('status', \App\Models\Payment::STATUS_PENDING)->sortByDesc('id')->first() : null;
    $instruction = $activePayment?->paymentInstruction();
    $paymentTypes = [
        'bank_transfer' => 'Virtual Account', 'echannel' => 'Mandiri Bill', 'permata' => 'Permata VA',
        'qris' => 'QRIS', 'gopay' => 'GoPay', 'shopeepay' => 'ShopeePay',
    ];
    $reasons = [
        'menunggu_transfer' => 'Tagihan sudah dibuat. Selesaikan transfer sebelum batas waktu.',
        'popup_ditutup'     => 'Jendela pembayaran ditutup sebelum selesai. Tagihan masih sama, lanjutkan kapan saja.',
        'ditolak'           => 'Pembayaran sebelumnya ditolak. Silakan coba metode lain.',
        'kedaluwarsa'       => 'Batas waktu pembayaran sudah habis.',
    ];
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

    @if ($awaiting)
        {{-- Hitung mundur berhenti dan berganti "Kedaluwarsa" tanpa reload; status resmi tetap dari server --}}
        <div
            x-data="{
                deadline: {{ $card->paymentDeadline()->getTimestampMs() }},
                remaining: null,
                timer: null,
                init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                destroy() { clearInterval(this.timer); },
                tick() {
                    this.remaining = Math.max(0, Math.floor((this.deadline - Date.now()) / 1000));
                    if (this.remaining === 0) clearInterval(this.timer);
                },
                get expired() { return this.remaining === 0; },
                get label() {
                    if (this.remaining === null) return '--:--:--';
                    const pad = (n) => String(n).padStart(2, '0');
                    return [Math.floor(this.remaining / 3600), Math.floor(this.remaining / 60) % 60, this.remaining % 60].map(pad).join(':');
                },
            }"
            class="space-y-3"
        >
            <div class="rounded-xl px-3 py-2 flex items-center justify-between gap-2" :class="expired ? 'bg-rose-50 border border-rose-200' : 'bg-amber-50 border border-amber-200'">
                <span class="text-[11px]" :class="expired ? 'text-rose-700' : 'text-amber-800'" x-text="expired ? 'Batas waktu pembayaran' : 'Bayar dalam'">Bayar dalam</span>
                <span class="font-mono text-sm font-extrabold tabular-nums" :class="expired ? 'text-rose-700' : 'text-amber-900'" x-text="expired ? 'Kedaluwarsa' : label" data-countdown>--:--:--</span>
            </div>

            @if ($activePayment?->payment_type || $instruction)
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-[11px] text-slate-700">
                    <p>Metode: <span class="font-bold">{{ $paymentTypes[$activePayment->payment_type] ?? $activePayment->payment_type }}</span></p>
                    @if ($instruction)
                        <p class="mt-0.5">
                            {{ $instruction['bank'] }}:
                            <span class="font-mono font-bold text-slate-900 select-all">{{ $instruction['number'] }}</span>
                            @if (! empty($instruction['biller_code'])) <span class="text-slate-500">(kode biller {{ $instruction['biller_code'] }})</span> @endif
                        </p>
                    @endif
                </div>
            @endif

            @if ($card->pending_reason && isset($reasons[$card->pending_reason]))
                <p class="text-[11px] text-slate-600" x-show="! expired">{{ $reasons[$card->pending_reason] }}</p>
            @endif
            <p class="text-[11px] text-rose-700" x-show="expired" x-cloak>Transaksi akan ditutup otomatis. Anda bisa memesan lagi setelahnya.</p>

            <div class="grid grid-cols-2 gap-2" x-show="! expired">
                <form
                    action="{{ route('pelanggan.pembayaran.cancel', $card) }}"
                    method="POST"
                    x-data="{ sending: false }"
                    x-on:submit="if (sending || ! confirm('Batalkan transaksi {{ $code }}? Tagihan yang belum dibayar akan ditutup.')) { $event.preventDefault(); return; } sending = true"
                >
                    @csrf
                    <button type="submit" :disabled="sending" class="w-full px-3 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold disabled:opacity-60">
                        Batalkan
                    </button>
                </form>
                <a href="{{ route('pelanggan.pembayaran.show', $card) }}" class="px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold text-center">
                    Bayar Sekarang
                </a>
            </div>
        </div>
    @elseif ($isCheckout && in_array($card->status, \App\Models\Checkout::CLOSED_UNPAID, true))
        <form action="{{ route('pelanggan.pembayaran.reorder', $card) }}" method="POST" x-data="{ sending: false }" x-on:submit="if (sending) { $event.preventDefault(); return; } sending = true">
            @csrf
            <button type="submit" :disabled="sending" class="w-full px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold disabled:opacity-60">
                Pesan Lagi
            </button>
        </form>
    @endif

    @unless ($hideDetailLink ?? false)
    <a href="{{ $detailUrl }}" class="flex items-center justify-center gap-1 text-xs font-bold text-sky-600 hover:text-sky-700">
        <span>Lihat Detail</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </a>
    @endunless
</article>
