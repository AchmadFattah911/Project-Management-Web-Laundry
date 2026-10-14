@extends('layouts.guest')

@section('title', 'Pembayaran ' . $checkout->code . ' - Laundry Coin')

@php
    // Keadaan panel pembayaran (transaksi lunas sudah dialihkan ke halaman selesai oleh controller):
    // awaiting = ada tagihan aktif yang belum lewat batas waktu
    // retry    = tagihan terakhir gagal / kadaluarsa, pelanggan bisa bayar lagi
    // new      = belum pernah membuat tagihan
    $state = match (true) {
        $payment && $payment->isAwaitingPayment() && $payment->checkout_url => 'awaiting',
        $payment !== null                                         => 'retry',
        default                                                   => 'new',
    };
    $methodLabel = $payment ? ($paymentMethods[$payment->method]['label'] ?? strtoupper($payment->method)) : null;
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
    <div class="pt-2 pb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-sky-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali ke Beranda
            </a>
            <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Selesaikan Pembayaran
            </h1>
            <p class="text-sm text-slate-500 mt-1">Kode transaksi <span class="font-mono font-bold text-slate-900">{{ $checkout->code }}</span></p>
        </div>
        <x-order-stepper current="bayar" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">
        <!-- Kolom kiri: ringkasan transaksi -->
        <div class="lg:col-span-3 space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs" aria-labelledby="ringkasan-title">
                <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-slate-100">
                    <h2 id="ringkasan-title" class="text-base font-extrabold text-slate-900">Ringkasan Pesanan</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $checkout->orders->count() }} pesanan dalam 1 transaksi · dibuat {{ $checkout->created_at->translatedFormat('d M Y, H:i') }}</p>
                </div>

                <ul class="divide-y divide-slate-100">
                    @foreach ($checkout->orders as $order)
                        <li class="px-5 sm:px-6 py-4 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900">{{ $order->service?->name }}</p>
                                <p class="text-[11px] text-slate-500">
                                    <span class="font-mono">{{ $order->order_code }}</span> · {{ $order->item_type }} · {{ $order->machine_qty }} × Rp {{ number_format($order->unit_price, 0, ',', '.') }}
                                </p>
                                @if ($order->notes)
                                    <p class="text-[11px] text-slate-600 italic mt-0.5">"{{ $order->notes }}"</p>
                                @endif
                            </div>
                            <p class="text-sm font-extrabold text-slate-900 shrink-0">Rp {{ number_format($order->total_price, 0, ',', '.') }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="px-5 sm:px-6 py-4 border-t border-slate-200 bg-slate-50/70 rounded-b-2xl flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Total Pembayaran</span>
                    <span class="text-xl font-extrabold text-sky-700">Rp {{ number_format($checkout->total_amount, 0, ',', '.') }}</span>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs p-5 sm:p-6">
                <h2 class="text-sm font-extrabold text-slate-900">Detail Pengantaran</h2>
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="flex items-start gap-2">
                        <x-icon name="calendar" class="w-4 h-4 text-sky-600 shrink-0" />
                        <div><dt class="text-slate-500">Rencana antar ke outlet</dt><dd class="font-bold text-slate-900">{{ $checkout->drop_off_date?->translatedFormat('l, d F Y') }}</dd></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <x-icon name="phone" class="w-4 h-4 text-sky-600 shrink-0" />
                        <div><dt class="text-slate-500">No. HP / WhatsApp</dt><dd class="font-bold text-slate-900">{{ $checkout->contact_phone }}</dd></div>
                    </div>
                    @if ($checkout->notes)
                        <div class="sm:col-span-2 flex items-start gap-2">
                            <x-icon name="edit" class="w-4 h-4 text-sky-600 shrink-0" />
                            <div><dt class="text-slate-500">Catatan</dt><dd class="font-semibold text-slate-900">{{ $checkout->notes }}</dd></div>
                        </div>
                    @endif
                </dl>
            </section>
        </div>

        <!-- Kolom kanan: panel pembayaran + tutorial -->
        <div class="lg:col-span-2 space-y-6 lg:sticky lg:top-24">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs p-5 sm:p-6" aria-labelledby="bayar-title">
                <h2 id="bayar-title" class="text-base font-extrabold text-slate-900">Pembayaran</h2>

                @if ($state === 'awaiting')
                    <div
                        x-data="paymentWatcher({
                            expiresAt: {{ $payment->expires_at ? $payment->expires_at->getTimestampMs() : 'null' }},
                            statusUrl: @js(route('pelanggan.pembayaran.status', $checkout)),
                        })"
                        class="mt-4 space-y-4"
                    >
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <x-badge variant="warning" size="sm">Menunggu Pembayaran</x-badge>
                                <span class="text-xs font-bold text-amber-900">{{ $methodLabel }}</span>
                            </div>
                            @if ($payment->expires_at)
                                <p class="mt-3 text-[11px] text-amber-900">Selesaikan sebelum</p>
                                <p class="text-2xl font-extrabold font-mono text-amber-900 tabular-nums" x-text="remainingLabel">--:--:--</p>
                                <p class="text-[11px] text-amber-800">{{ $payment->expires_at->translatedFormat('d M Y, H:i') }}</p>
                            @endif
                            <p x-show="checking" x-cloak class="mt-2 text-[11px] text-amber-800 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                Memantau status pembayaran secara otomatis...
                            </p>
                        </div>

                        <template x-if="! expired">
                            <a href="{{ $payment->checkout_url }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shadow-sm">
                                Lanjutkan ke Halaman Pembayaran <x-icon name="arrow-right" class="w-4 h-4" />
                            </a>
                        </template>
                        <template x-if="expired">
                            <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800">
                                Batas waktu pembayaran habis. <button type="button" x-on:click="window.location.reload()" class="font-bold underline">Muat ulang</button> untuk membuat tagihan baru.
                            </div>
                        </template>

                        <!-- Ganti metode = tagihan baru dengan key baru; tagihan lama otomatis ditutup -->
                        <div x-data="{ change: false }" class="border-t border-slate-100 pt-3">
                            <button type="button" x-on:click="change = ! change" class="text-xs font-bold text-slate-600 hover:text-sky-700">
                                <span x-text="change ? 'Batal ganti metode' : 'Ganti metode pembayaran'">Ganti metode pembayaran</span>
                            </button>
                            <div x-show="change" x-collapse x-cloak class="mt-3 space-y-3">
                                <p class="text-[11px] text-slate-500">Tagihan lama akan ditutup dan diganti tagihan baru. Jangan membayar tagihan lama setelah ini.</p>
                                @include('pelanggan.pembayaran.partials.pay-form', ['buttonLabel' => 'Buat Tagihan Baru', 'selected' => $payment->method, 'idPrefix' => 'pay-change'])
                            </div>
                        </div>
                    </div>

                @else
                    @if ($state === 'retry')
                        @php $failedStatus = $payment->displayStatus(); @endphp
                        <div class="mt-4">
                            <x-alert
                                :type="$failedStatus === 'failed' ? 'error' : 'warning'"
                                :dismissible="false"
                                :message="$failedStatus === 'failed'
                                    ? 'Pembayaran sebelumnya gagal. Tidak ada dana yang terpotong; silakan coba lagi.'
                                    : 'Tagihan sebelumnya sudah kadaluarsa. Silakan buat tagihan baru untuk membayar.'"
                            />
                        </div>
                    @else
                        <p class="mt-1 text-xs text-slate-500">Pilih metode pembayaran cashless, lalu Anda akan diarahkan ke halaman pembayaran.</p>
                    @endif

                    <div class="mt-4">
                        @include('pelanggan.pembayaran.partials.pay-form', [
                            'buttonLabel' => $state === 'retry' ? 'Bayar Lagi' : 'Bayar Sekarang',
                            'selected'    => $payment?->method,
                        ])
                    </div>
                @endif
            </section>

            <!-- Mini tutorial cara membayar -->
            <section class="rounded-2xl border border-sky-200 bg-sky-50/60 p-5" aria-labelledby="tutorial-bayar-title">
                <h2 id="tutorial-bayar-title" class="flex items-center gap-2 text-sm font-extrabold text-slate-900">
                    <x-icon name="info" class="w-4 h-4 text-sky-600" /> Cara Membayar
                </h2>
                <ol class="mt-3 space-y-3">
                    @foreach ([
                        ['Pilih metode', 'Transfer Bank atau E-Wallet/QRIS, lalu klik "Bayar Sekarang".'],
                        ['Bayar di halaman pembayaran', 'Ikuti instruksi di halaman pembayaran sebelum batas waktu habis.'],
                        ['Kembali otomatis', 'Status di halaman ini diperbarui otomatis. Setelah lunas, antar cucian ke outlet sesuai tanggal.'],
                    ] as $i => [$title, $text])
                        <li class="flex gap-3">
                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-extrabold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-xs font-bold text-slate-900">{{ $title }}</p>
                                <p class="text-[11px] text-slate-600 leading-snug">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3 text-[11px] text-slate-600 border-t border-sky-200 pt-3">
                    Klik "Bayar" lebih dari sekali atau koneksi terputus? Tenang, setiap permintaan pembayaran punya kode unik sehingga Anda tidak akan ditagih dua kali.
                </p>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Countdown batas bayar + pantau status (notifikasi gateway bisa tiba beberapa detik setelah pelanggan kembali).
    document.addEventListener('alpine:init', () => {
        Alpine.data('paymentWatcher', ({ expiresAt, statusUrl }) => ({
            remaining: null,
            expired: false,
            checking: true,
            polls: 0,
            init() {
                this.tick();
                this.timer = setInterval(() => this.tick(), 1000);
                this.poller = setInterval(() => this.poll(), 5000);
            },
            destroy() {
                clearInterval(this.timer);
                clearInterval(this.poller);
            },
            tick() {
                if (! expiresAt) return;
                this.remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                if (this.remaining === 0) {
                    this.expired = true;
                    clearInterval(this.timer);
                }
            },
            get remainingLabel() {
                if (this.remaining === null) return '--:--:--';
                const pad = (n) => String(n).padStart(2, '0');
                return [Math.floor(this.remaining / 3600), Math.floor(this.remaining / 60) % 60, this.remaining % 60].map(pad).join(':');
            },
            async poll() {
                // Berhenti memantau setelah ±10 menit agar tidak membebani server
                if (++this.polls > 120 || this.expired) {
                    this.checking = false;
                    clearInterval(this.poller);
                    return;
                }
                try {
                    const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                    if (! res.ok) return;
                    const data = await res.json();
                    if (data.paid || data.payment_status !== 'pending') {
                        window.location.reload();
                    }
                } catch (e) {
                    // Koneksi terputus sementara: coba lagi di putaran berikutnya
                }
            },
        }));
    });
</script>
@endpush
