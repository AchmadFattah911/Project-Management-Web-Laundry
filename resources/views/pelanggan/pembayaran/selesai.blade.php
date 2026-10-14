@extends('layouts.guest')

@section('title', 'Pembayaran Berhasil ' . $checkout->code . ' - Laundry Coin')

@php
    $stepLabels = ['cuci' => 'Cuci', 'kering' => 'Kering', 'lipat' => 'Lipat'];
    $methodLabel = $payment ? ($paymentMethods[$payment->method]['label'] ?? strtoupper($payment->method)) : '-';
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 space-y-6">
    <div class="pt-2 flex justify-end">
        <x-order-stepper current="selesai" />
    </div>

    <!-- 1. Banner berhasil -->
    <section class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                <x-icon name="check" class="w-8 h-8" />
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Pembayaran Berhasil!</h1>
                <p class="text-sm text-emerald-50 mt-1">Terima kasih, transaksi Anda sudah lunas dan pesanan tercatat di Laundry Coin.</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-[11px] uppercase tracking-wider text-emerald-100">Total dibayar</p>
                <p class="text-2xl font-extrabold">Rp {{ number_format($checkout->total_amount, 0, ',', '.') }}</p>
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="rounded-xl bg-white/15 px-3 py-2.5">
                <dt class="text-emerald-100">Kode Transaksi</dt>
                <dd class="font-mono font-bold">{{ $checkout->code }}</dd>
            </div>
            <div class="rounded-xl bg-white/15 px-3 py-2.5">
                <dt class="text-emerald-100">Metode</dt>
                <dd class="font-bold">{{ $methodLabel }}</dd>
            </div>
            <div class="rounded-xl bg-white/15 px-3 py-2.5">
                <dt class="text-emerald-100">Dibayar</dt>
                <dd class="font-bold">{{ $payment?->paid_at?->translatedFormat('d M Y, H:i') ?? '-' }}</dd>
            </div>
            <div class="rounded-xl bg-white/15 px-3 py-2.5">
                <dt class="text-emerald-100">Antar ke Outlet</dt>
                <dd class="font-bold">{{ $checkout->drop_off_date?->translatedFormat('d M Y') }}</dd>
            </div>
        </dl>
    </section>

    <!-- 2. Langkah berikutnya -->
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <h2 class="flex items-center gap-2 text-sm font-extrabold text-amber-900">
            <x-icon name="map-pin" class="w-4 h-4" /> Langkah Berikutnya
        </h2>
        <ol class="mt-2 space-y-1.5 text-xs text-amber-900 list-decimal list-inside">
            <li>Antar cucian ke outlet pada <strong>{{ $checkout->drop_off_date?->translatedFormat('l, d F Y') }}</strong>.</li>
            <li>Tunjukkan <strong>kode transaksi {{ $checkout->code }}</strong> (atau kode pesanan di bawah) kepada petugas.</li>
            <li>Petugas memproses cucian; perkembangan tiap tahap bisa dipantau dari riwayat pemesanan.</li>
        </ol>
    </section>

    <!-- 3. Riwayat pemesanan dari transaksi ini -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-xs" aria-labelledby="riwayat-transaksi-title">
        <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-slate-100">
            <h2 id="riwayat-transaksi-title" class="text-base font-extrabold text-slate-900">Riwayat Pemesanan Layanan</h2>
            <p class="text-xs text-slate-500 mt-0.5">Pesanan dari transaksi ini ({{ $checkout->orders->count() }} layanan)</p>
        </div>

        <ul class="divide-y divide-slate-100">
            @foreach ($checkout->orders as $order)
                <li>
                    <a href="{{ route('pelanggan.orders.show', $order) }}" class="flex items-start gap-3 px-5 sm:px-6 py-4 group hover:bg-slate-50/70 transition-colors">
                        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                            <x-icon name="washer" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-bold text-slate-900 group-hover:text-sky-700">{{ $order->service?->name ?? 'Layanan' }}</p>
                                <x-badge :status="$order->status" size="sm" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                <span class="font-mono font-semibold">{{ $order->order_code }}</span> · {{ $order->item_type }} · {{ $order->machine_qty }} mesin
                            </p>
                            @if ($order->orderProcesses->isNotEmpty())
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    @foreach ($order->orderProcesses as $process)
                                        <span @class([
                                            'inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold',
                                            'bg-emerald-100 text-emerald-700' => $process->status === 'selesai',
                                            'bg-slate-100 text-slate-500' => $process->status !== 'selesai',
                                        ])>
                                            @if ($process->status === 'selesai') <x-icon name="check" class="w-3 h-3" /> @endif
                                            {{ $stepLabels[$process->process] ?? ucfirst($process->process) }}
                                        </span>
                                        @if (! $loop->last) <span class="text-slate-300">→</span> @endif
                                    @endforeach
                                </div>
                            @endif
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <p class="text-sm font-extrabold text-slate-900">Rp {{ number_format($order->total_price, 0, ',', '.') }}</p>
                                <x-badge payment="paid" size="sm" />
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($recentOrders->isNotEmpty())
            <div class="px-5 sm:px-6 pt-5 pb-2 border-t border-slate-200">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Pesanan Anda Sebelumnya</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($recentOrders as $order)
                    @php $payStatus = $order->paymentForDisplay()?->displayStatus() ?? 'pending'; @endphp
                    <li>
                        <a href="{{ route('pelanggan.orders.show', $order) }}" class="flex items-center gap-3 px-5 sm:px-6 py-3 group hover:bg-slate-50/70 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate group-hover:text-sky-700">{{ $order->service?->name ?? 'Layanan' }}</p>
                                <p class="text-[11px] text-slate-500"><span class="font-mono">{{ $order->order_code }}</span> · {{ $order->created_at->format('d M Y') }}</p>
                            </div>
                            <x-badge :status="$order->status" size="sm" />
                            <x-badge :payment="$payStatus" size="sm" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <!-- 4. Mini tutorial / deskripsi riwayat lengkap -->
    <section class="rounded-2xl border border-sky-200 bg-sky-50/70 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-sky-600 text-white flex items-center justify-center shrink-0">
            <x-icon name="user" class="w-5 h-5" />
        </div>
        <p class="flex-1 text-sm text-slate-700 leading-relaxed">
            Jika ingin melihat semua riwayat pemesanan Anda, silakan kembali ke halaman utama dan klik <strong>profil pengguna</strong> Anda!
        </p>
        <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shrink-0">
            Kembali ke Beranda
        </a>
    </section>
</div>
@endsection
