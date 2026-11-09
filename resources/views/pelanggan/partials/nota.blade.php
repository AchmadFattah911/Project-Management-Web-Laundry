{{--
    Nota pesanan untuk ditunjukkan ke petugas outlet: 1 transaksi keranjang (Checkout) atau 1 pesanan di outlet (Order).
    Variabel: $card (Checkout|Order)
--}}
@php
    $isCheckout = $card instanceof \App\Models\Checkout;
    $group = \App\Services\CustomerOrderHistory::tabFor($card);
    $timezone = config('laundry.report_timezone', 'Asia/Jakarta');
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');

    $code = $isCheckout ? $card->code : $card->order_code;
    $items = $isCheckout ? $card->orders : collect([$card]);
    $total = $isCheckout ? $card->total_amount : $card->total_price;
    $payment = $card->paymentForDisplay();
    $paid = $card->isPaid();
    $paidAt = $paid ? (($isCheckout ? $card->paid_at : null) ?? $payment?->paid_at) : null;

    $customerName = $isCheckout ? $card->user?->name : ($card->customer_name ?? $card->user?->name);
    $customerPhone = $isCheckout ? ($card->contact_phone ?? $card->user?->phone) : ($card->customer_phone ?? $card->user?->phone);

    $paymentTypes = [
        'bank_transfer' => 'Virtual Account', 'echannel' => 'Mandiri Bill', 'permata' => 'Permata VA',
        'qris' => 'QRIS', 'gopay' => 'GoPay', 'shopeepay' => 'ShopeePay', 'credit_card' => 'Kartu Kredit',
    ];
    $paymentLabel = $paid && $payment
        ? ($paymentTypes[$payment->payment_type] ?? config('laundry.online_payment_methods.' . $payment->method . '.label', ucfirst((string) $payment->method)))
        : null;

    $closedLabel = match ($card->status) {
        \App\Models\Checkout::STATUS_EXPIRED => 'Kedaluwarsa',
        \App\Models\Checkout::STATUS_FAILED  => 'Gagal',
        default                              => 'Dibatalkan',
    };
    $stamp = match (true) {
        $group === 'dibatalkan' => ['text-rose-600 border-rose-300', $closedLabel],
        $paid                   => ['text-emerald-600 border-emerald-300', 'Pembayaran Lunas'],
        default                 => ['text-amber-600 border-amber-300', 'Bayar di Kasir'],
    };

    $staffWa = (string) config('laundry.staff_whatsapp', '');
    // wa.me butuh kode negara: 0812... → 62812...
    $staffWaDigits = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $staffWa));
    $waText = rawurlencode("Halo Laundry Coin, saya mau tanya pesanan {$code}.");
@endphp

<article class="bg-white rounded-2xl border border-slate-200 overflow-hidden" data-nota="{{ $code }}">
    {{-- Kepala nota --}}
    <div class="px-5 pt-5 pb-4 text-center border-b border-dashed border-slate-300">
        <p class="text-base font-extrabold text-sky-700 tracking-tight">{{ config('laundry.store_name', 'Laundry Coin') }}</p>
        <p class="text-[11px] text-slate-500">{{ config('laundry.address') }}</p>
        <p class="mt-3 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Nota Pesanan</p>
        <p class="font-mono text-lg font-extrabold text-slate-900 select-all">{{ $code }}</p>
        <span class="mt-2 inline-block px-3 py-1 rounded-lg border-2 text-[11px] font-extrabold uppercase tracking-wider -rotate-2 {{ $stamp[0] }}">{{ $stamp[1] }}</span>
    </div>

    {{-- Data pesanan & pelanggan --}}
    <dl class="px-5 py-4 space-y-2 text-xs border-b border-dashed border-slate-300">
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Waktu Pemesanan</dt>
            <dd class="font-bold text-slate-800 text-right">{{ $card->created_at->timezone($timezone)->translatedFormat('d M Y, H:i') }} WIB</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Waktu Pembayaran</dt>
            <dd class="font-bold text-slate-800 text-right" data-nota-paid-at>
                {{ $paidAt ? $paidAt->timezone($timezone)->translatedFormat('d M Y, H:i') . ' WIB' : '-' }}
            </dd>
        </div>
        @if ($paymentLabel)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Metode Pembayaran</dt>
                <dd class="font-bold text-slate-800 text-right">{{ $paymentLabel }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Nama Pemesan</dt>
            <dd class="font-bold text-slate-800 text-right">{{ $customerName ?: '-' }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">No. Telepon</dt>
            <dd class="font-mono font-bold text-slate-800 text-right">{{ $customerPhone ?: '-' }}</dd>
        </div>
        @if ($isCheckout && $card->drop_off_date)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Antar ke Outlet</dt>
                <dd class="font-bold text-slate-800 text-right">{{ $card->drop_off_date->translatedFormat('d M Y') }}</dd>
            </div>
        @endif
    </dl>

    {{-- Rincian layanan & harga --}}
    <div class="px-5 py-4 border-b border-dashed border-slate-300">
        <ul class="space-y-3">
            @foreach ($items as $item)
                <li class="flex items-start justify-between gap-3 text-xs">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-800">{{ $item->serviceName() }}</p>
                        <p class="text-[11px] text-slate-500">
                            {{ $item->machine_qty }} &times; {{ $rupiah($item->unit_price) }}
                            @if ($isCheckout) &middot; <span class="font-mono">{{ $item->order_code }}</span> @endif
                        </p>
                        @if ($paid && $group !== 'dibatalkan')
                            <x-badge :status="$item->status" size="sm" class="mt-1" />
                        @endif
                    </div>
                    <p class="font-bold text-slate-900 shrink-0">{{ $rupiah($item->total_price) }}</p>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-700">Total</span>
            <span class="text-lg font-extrabold text-slate-900">{{ $rupiah($total) }}</span>
        </div>

        @if ($card->notes)
            <p class="mt-3 text-[11px] text-slate-600"><span class="font-bold">Catatan:</span> {{ $card->notes }}</p>
        @endif
    </div>

    {{-- Kontak pegawai & petunjuk di outlet --}}
    <div class="px-5 py-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] text-slate-500">WhatsApp Pegawai Laundry Coin</p>
                <p class="font-mono text-sm font-bold text-slate-900">{{ $staffWa }}</p>
            </div>
            @if ($staffWaDigits !== '')
                <a href="https://wa.me/{{ $staffWaDigits }}?text={{ $waText }}" target="_blank" rel="noopener" class="shrink-0 px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors print:hidden">
                    Chat WA
                </a>
            @endif
        </div>

        @if ($group === 'dibatalkan')
            <p class="text-[11px] text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2">Transaksi ini sudah ditutup dan tidak berlaku sebagai bukti pesanan.</p>
        @else
            <p class="text-[11px] text-sky-800 bg-sky-50 border border-sky-200 rounded-xl px-3 py-2">Tunjukkan nota ini ke petugas saat mengantar atau mengambil cucian di outlet.</p>
        @endif
    </div>
</article>
