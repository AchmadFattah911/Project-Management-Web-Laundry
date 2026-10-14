@extends('layouts.guest')

@php
    // Tagihan transaksi keranjang (checkout) atau tagihan order tunggal lama
    $checkout = $payment->checkout;
    $order = $payment->order;
    $code = $checkout?->code ?? $order?->order_code;
    $serviceNames = $checkout
        ? $checkout->orders->map(fn ($o) => $o->service?->name . ' ×' . $o->machine_qty)->implode(', ')
        : $order?->service?->name;
    $backUrl = $checkout ? route('pelanggan.pembayaran.show', $checkout) : route('pelanggan.orders.show', $order);
    $methodLabel = $methods[$payment->method]['label'] ?? strtoupper($payment->method);
    $status = $payment->displayStatus();
@endphp

@section('title', 'Pembayaran ' . $code . ' - Laundry Coin')

@section('content')
<div class="max-w-lg mx-auto px-4 py-10 space-y-5">
    <!-- Penanda mode testing agar tidak dikira halaman pembayaran asli -->
    <div class="rounded-xl border-2 border-dashed border-amber-400 bg-amber-50 p-4 text-xs text-amber-900">
        <p class="font-extrabold uppercase tracking-wider">Mode Testing - Dummy Payment Gateway</p>
        <p class="mt-1">Halaman ini hanya simulasi untuk pengujian. Tidak ada uang sungguhan yang ditagih atau berpindah.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-sky-900 to-slate-900 text-white">
            <p class="text-xs text-sky-200">Total Tagihan</p>
            <p class="text-3xl font-extrabold">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
        </div>

        <dl class="px-6 py-5 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
            <div>
                <dt class="text-slate-500 font-semibold">{{ $checkout ? 'Kode Transaksi' : 'Kode Pesanan' }}</dt>
                <dd class="font-bold text-slate-900 font-mono">{{ $code }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 font-semibold">Layanan</dt>
                <dd class="font-bold text-slate-900">{{ $serviceNames }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 font-semibold">Metode</dt>
                <dd class="font-bold text-slate-900">{{ $methodLabel }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 font-semibold">Status</dt>
                <dd><x-badge :payment="$status" size="sm" /></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500 font-semibold">Reference</dt>
                <dd class="font-bold text-slate-900 font-mono break-all">{{ $payment->reference }}</dd>
            </div>
            @if ($payment->expires_at)
                <div class="col-span-2">
                    <dt class="text-slate-500 font-semibold">Batas Pembayaran</dt>
                    <dd class="font-bold text-slate-900">{{ $payment->expires_at->format('d M Y, H:i') }}</dd>
                </div>
            @endif
        </dl>

        <div class="px-6 pb-6">
            @if ($status === 'pending')
                <form action="{{ route('payments.dummy.simulate', $payment->reference) }}" method="POST">
                    @csrf
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Simulasikan Hasil Pembayaran</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @foreach ([
                            'paid'    => ['label' => 'Bayar Berhasil', 'class' => 'bg-emerald-600 hover:bg-emerald-700 text-white'],
                            'failed'  => ['label' => 'Bayar Gagal', 'class' => 'bg-rose-600 hover:bg-rose-700 text-white'],
                            'expired' => ['label' => 'Kadaluarsa', 'class' => 'bg-slate-200 hover:bg-slate-300 text-slate-800'],
                        ] as $result => $btn)
                            <button type="submit" name="result" value="{{ $result }}" class="w-full px-4 py-2.5 rounded-xl font-bold text-xs transition-colors {{ $btn['class'] }}">
                                {{ $btn['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <label class="mt-3 flex items-start gap-2 text-[11px] text-slate-600 cursor-pointer">
                        <input type="checkbox" name="duplicate" value="1" class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        <span>Kirim notifikasi webhook 2x (simulasi gateway retry). Sistem harus tetap memprosesnya hanya 1x.</span>
                    </label>
                </form>
            @else
                <p class="text-xs text-slate-600">Tagihan ini sudah tidak bisa dibayar (status: <strong>{{ $status }}</strong>).</p>
            @endif

            <a href="{{ $backUrl }}" class="mt-4 inline-flex text-xs font-bold text-sky-700 hover:underline">
                {{ $checkout ? 'Kembali ke halaman pembayaran' : 'Kembali ke detail pesanan' }}
            </a>
        </div>
    </div>
</div>
@endsection
