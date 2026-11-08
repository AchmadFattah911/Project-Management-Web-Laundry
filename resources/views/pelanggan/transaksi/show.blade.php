@extends('layouts.dashboard')

@section('title', 'Transaksi ' . $checkout->code)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <a href="{{ route('pelanggan.orders.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 hover:text-sky-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Kembali ke Riwayat</span>
        </a>
    </div>

    @include('pelanggan.partials.transaction-card', ['card' => $checkout, 'hideDetailLink' => true])

    <section class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
        <h2 class="text-sm font-extrabold text-slate-900 mb-3">Rincian Pesanan</h2>
        <ul class="divide-y divide-slate-100">
            @foreach ($checkout->orders as $order)
                <li class="py-2.5 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800">{{ $order->serviceName() }}</p>
                        <p class="text-[11px] text-slate-500">
                            <span class="font-mono">{{ $order->order_code }}</span> &middot; {{ $order->machine_qty }} &times; Rp {{ number_format((float) $order->unit_price, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-right shrink-0 space-y-1">
                        <p class="text-xs font-extrabold text-slate-900">Rp {{ number_format((float) $order->total_price, 0, ',', '.') }}</p>
                        @if ($checkout->isPaid())
                            <x-badge :status="$order->status" size="sm" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($payment?->isPaid())
            <p class="mt-3 text-[11px] text-slate-600">
                Dibayar {{ $payment->paid_at?->timezone(config('laundry.report_timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') }} WIB
                lewat {{ $payment->payment_type ?? $payment->method }}.
            </p>
        @endif
    </section>

    @include('pelanggan.partials.timeline', ['timeline' => $timeline])
</div>
@endsection
