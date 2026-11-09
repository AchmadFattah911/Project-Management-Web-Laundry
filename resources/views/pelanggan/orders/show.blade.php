@extends('layouts.mobile', ['bottomNavActive' => 'transaksi'])

@section('title', 'Nota ' . $order->order_code)

@section('content')
@include('pelanggan.partials.page-header', ['title' => 'Detail Pemesanan', 'back' => route('pelanggan.orders.index')])

<div class="px-4 py-4 space-y-4">
    @include('pelanggan.partials.nota', ['card' => $order])

    @if ($isPaid)
        <button type="button" onclick="window.print()" class="w-full py-3 rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors print:hidden">
            Simpan / Cetak Nota
        </button>
    @else
        <!-- Pembayaran (pesanan lama yang belum lunas) -->
        <x-card title="Pembayaran" subtitle="Bayar di kasir outlet atau secara online" class="print:hidden">
            @if ($order->checkout)
                {{-- Order dari keranjang dibayar sekaligus per transaksi, bukan per order --}}
                <div class="space-y-3">
                    <div class="flex items-start gap-3 p-4 rounded-xl bg-sky-50/70 border border-sky-200 text-xs">
                        <x-icon name="info" class="w-5 h-5 text-sky-600 shrink-0" />
                        <div>
                            <p class="font-bold text-sky-900">Bagian dari transaksi <span class="font-mono">{{ $order->checkout->code }}</span></p>
                            <p class="text-sky-800/90 mt-0.5">Pesanan ini dibayar bersama {{ $order->checkout->orders()->count() }} pesanan dalam transaksi yang sama, total Rp {{ number_format($order->checkout->total_amount, 0, ',', '.') }}.</p>
                        </div>
                    </div>
                    <a
                        href="{{ route('pelanggan.pembayaran.show', $order->checkout) }}"
                        class="inline-flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
                    >
                        Bayar di Halaman Transaksi
                    </a>
                </div>
            @elseif ($payment?->isAwaitingPayment() && $payment->checkout_url)
                <div class="space-y-3">
                    <div class="flex items-start gap-3 p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-xs">
                        <x-icon name="clock" class="w-5 h-5 text-amber-600 shrink-0" />
                        <div>
                            <p class="font-bold text-amber-900">Menunggu Pembayaran</p>
                            <p class="text-amber-800/90 mt-0.5">
                                Selesaikan pembayaran Rp {{ number_format($payment->amount, 0, ',', '.') }} via {{ config('laundry.online_payment_methods.' . $payment->method . '.label', strtoupper($payment->method)) }}
                                @if ($payment->expires_at)
                                    sebelum {{ $payment->expires_at->format('d M Y, H:i') }}.
                                @endif
                            </p>
                        </div>
                    </div>
                    <a
                        href="{{ $payment->checkout_url }}"
                        class="inline-flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
                    >
                        Bayar Sekarang
                    </a>
                </div>
            @else
                <form action="{{ route('pelanggan.orders.pay', $order) }}" method="POST" class="space-y-4">
                    @csrf
                    @php
                        $failedAttempt = $payment && in_array($payment->displayStatus(), ['failed', 'expired'], true);
                    @endphp
                    @if ($failedAttempt)
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-rose-50/70 border border-rose-200 text-xs">
                            <x-icon name="alert" class="w-5 h-5 text-rose-600 shrink-0" />
                            <div>
                                <p class="font-bold text-rose-900">Pembayaran {{ $payment->displayStatus() === 'failed' ? 'Gagal' : 'Kadaluarsa' }}</p>
                                <p class="text-rose-800/90 mt-0.5">Silakan pilih metode dan buat tagihan baru untuk melanjutkan pembayaran.</p>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-600">Pesanan ini belum memiliki tagihan online. Pilih metode untuk membayar Rp {{ number_format($order->total_price, 0, ',', '.') }}, atau bayar langsung di kasir outlet.</p>
                    @endif

                    <x-payment-method-picker id-prefix="show-pm" :selected="$payment?->method" />

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
                    >
                        {{ $failedAttempt ? 'Buat Tagihan Baru' : 'Bayar Sekarang' }}
                    </button>
                </form>
            @endif
        </x-card>
    @endif

    <!-- Tahapan pengerjaan (khusus layanan Drop-Off) -->
    @if ($order->orderProcesses->isNotEmpty())
        <section class="bg-white rounded-2xl border border-slate-200 p-4 print:hidden">
            <h2 class="text-sm font-extrabold text-slate-900 mb-3">Tahapan Pengerjaan</h2>
            <ol class="space-y-2">
                @foreach ($order->orderProcesses as $index => $proc)
                    @php $isFinished = $proc->status === 'selesai'; @endphp
                    <li class="flex items-start gap-3 p-3 rounded-xl border {{ $isFinished ? 'bg-emerald-50/40 border-emerald-200/80' : 'bg-slate-50/70 border-slate-200/80' }}">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 {{ $isFinished ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                            @if ($isFinished)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-900 uppercase">Proses {{ $proc->process }}</p>
                            <p class="text-[11px] text-slate-500">
                                @if ($isFinished)
                                    Selesai {{ $proc->updated_at->timezone(config('laundry.report_timezone', 'Asia/Jakarta'))->format('d M Y, H:i') }}
                                    @if ($proc->pegawai) oleh {{ $proc->pegawai->name }} @endif
                                @else
                                    Menunggu giliran pengerjaan
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <!-- Riwayat status dari order_status_logs -->
    <div class="print:hidden">
        @include('pelanggan.partials.timeline', ['timeline' => $timeline])
    </div>
</div>
@endsection
