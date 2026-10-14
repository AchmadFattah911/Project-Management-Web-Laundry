@extends('layouts.dashboard')

@section('title', 'Detail Pesanan #' . $order->order_code)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Navigation & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a
                    href="{{ route('pelanggan.orders.index') }}"
                    class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 hover:text-sky-700"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Kembali ke Riwayat</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-mono">
                    #{{ $order->order_code }}
                </h1>
                <x-badge :status="$order->status" size="md" />
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <a
                href="{{ route('tracking', ['order_code' => $order->order_code]) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition-colors"
            >
                <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Halaman Tracking Publik</span>
            </a>
        </div>
    </div>

    <!-- Status Overview Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Layanan</span>
            <p class="text-base font-bold text-slate-900 mt-1">{{ $order->service?->name ?? 'Layanan' }}</p>
            <span class="text-[11px] text-slate-500">{{ $order->service?->category ?? 'Laundromat' }}</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kapasitas / Unit</span>
            <p class="text-base font-bold text-slate-900 mt-1">
                @if (($order->service?->unit ?? 'Mesin') === 'Mesin')
                    {{ $order->machine_qty }} × Mesin
                @else
                    {{ $order->machine_qty }} Paket
                @endif
            </p>
            <span class="text-[11px] text-slate-500">Rp {{ number_format($order->unit_price, 0, ',', '.') }} / unit</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Biaya</span>
            <p class="text-xl font-extrabold text-sky-700 mt-1">
                Rp {{ number_format($order->total_price, 0, ',', '.') }}
            </p>
            <div class="mt-1">
                @php
                    $payStatus = $payment?->displayStatus() ?? 'pending';
                @endphp
                <x-badge :payment="$payStatus" size="sm" />
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Waktu Pemesanan</span>
            <p class="text-sm font-bold text-slate-800 mt-1">
                {{ $order->created_at->format('d M Y') }}
            </p>
            <span class="text-[11px] text-slate-500">Pukul {{ $order->created_at->format('H:i') }}</span>
        </div>
    </div>

    <!-- Pembayaran Online -->
    <x-card title="Pembayaran" subtitle="Pembayaran online cashless untuk pesanan ini">
        @if ($isPaid)
            <div class="flex items-start gap-3 p-4 rounded-xl bg-emerald-50/70 border border-emerald-200 text-xs">
                <x-icon name="check" class="w-5 h-5 text-emerald-600 shrink-0" />
                <div>
                    <p class="font-bold text-emerald-900">Pembayaran Lunas</p>
                    <p class="text-emerald-800/90 mt-0.5">
                        Dibayar {{ $payment->paid_at?->format('d M Y, H:i') }} via {{ config('laundry.online_payment_methods.' . $payment->method . '.label', strtoupper($payment->method)) }}.
                        @if ($payment->reference)
                            Ref: <span class="font-mono">{{ $payment->reference }}</span>
                        @endif
                    </p>
                </div>
            </div>
        @elseif ($order->checkout)
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
                    class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
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
                    class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
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
                    class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-xs transition-colors"
                >
                    {{ $failedAttempt ? 'Buat Tagihan Baru' : 'Bayar Sekarang' }}
                </button>
            </form>
        @endif
    </x-card>

    <!-- Timeline Tahapan Pengerjaan (Khusus Non-Self Service) -->
    @if ($order->orderProcesses->isNotEmpty())
        <x-card title="Tahapan Proses Pengerjaan" subtitle="Pantau kemajuan cuci, kering, hingga pelipatan pakaian Anda">
            <div class="space-y-4">
                @foreach ($order->orderProcesses as $index => $proc)
                    @php
                        $isFinished = $proc->status === 'selesai';
                    @endphp
                    <div class="flex items-start gap-4 p-4 rounded-xl border {{ $isFinished ? 'bg-emerald-50/40 border-emerald-200/80' : 'bg-slate-50/70 border-slate-200/80' }}">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 {{ $isFinished ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-700' }}">
                            @if ($isFinished)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-sm font-bold text-slate-900 uppercase">
                                    Proses {{ $proc->process }}
                                </h4>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $isFinished ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $isFinished ? 'Selesai' : 'Sedang Menunggu / Dikerjakan' }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-500 mt-1">
                                @if ($isFinished)
                                    Selesai diproses pada {{ $proc->updated_at->format('d M Y, H:i') }}
                                    @if ($proc->pegawai)
                                        oleh staf {{ $proc->pegawai->name }}
                                    @endif
                                @else
                                    Menunggu giliran pengerjaan oleh staf mesin
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @else
        <div class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 text-amber-900 text-xs flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="font-bold">Layanan Self Laundry</p>
                <p class="text-amber-800/90 mt-0.5">
                    Pesanan ini adalah layanan cuci mandiri. Silakan gunakan mesin cuci/pengering yang tersedia langsung di outlet.
                </p>
            </div>
        </div>
    @endif

    <!-- Catatan & Informasi Tambahan -->
    <x-card title="Informasi Pesanan" subtitle="Rincian tambahan dan instruksi pelanggan">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-xs">
            <div>
                <dt class="font-semibold text-slate-500">Nama Pemesan</dt>
                <dd class="font-bold text-slate-900 mt-0.5">{{ $order->customer_name ?? $order->user?->name ?? 'Pelanggan' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Nomor Telepon</dt>
                <dd class="font-bold text-slate-900 mt-0.5">{{ $order->customer_phone ?? $order->user?->phone ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Metode Pembayaran</dt>
                <dd class="font-bold text-slate-900 mt-0.5">{{ $payment ? config('laundry.online_payment_methods.' . $payment->method . '.label', strtoupper($payment->method)) : 'Belum dipilih' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Status Pembayaran</dt>
                <dd class="mt-0.5"><x-badge :payment="$payStatus" size="sm" /></dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-semibold text-slate-500">Catatan Khusus</dt>
                <dd class="text-slate-700 mt-0.5 italic">{{ $order->notes ?: 'Tidak ada catatan tambahan.' }}</dd>
            </div>
        </dl>
    </x-card>

</div>
@endsection
