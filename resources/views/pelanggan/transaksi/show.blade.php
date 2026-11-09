@extends('layouts.mobile', ['bottomNavActive' => 'transaksi'])

@section('title', 'Nota ' . $checkout->code)

@section('content')
@include('pelanggan.partials.page-header', ['title' => 'Detail Pemesanan', 'back' => route('pelanggan.orders.index')])

<div class="px-4 py-4 space-y-4">
    @include('pelanggan.partials.nota', ['card' => $checkout])

    @if (in_array($checkout->status, \App\Models\Checkout::CLOSED_UNPAID, true))
        <form action="{{ route('pelanggan.pembayaran.reorder', $checkout) }}" method="POST" x-data="{ sending: false }" x-on:submit="if (sending) { $event.preventDefault(); return; } sending = true">
            @csrf
            <button type="submit" :disabled="sending" class="w-full py-3 rounded-2xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold disabled:opacity-60 transition-colors">
                Pesan Lagi
            </button>
        </form>
    @else
        <button type="button" onclick="window.print()" class="w-full py-3 rounded-2xl border border-slate-200 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors print:hidden">
            Simpan / Cetak Nota
        </button>
    @endif

    <div class="print:hidden">
        @include('pelanggan.partials.timeline', ['timeline' => $timeline])
    </div>
</div>
@endsection
