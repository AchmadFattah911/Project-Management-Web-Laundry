@extends('layouts.guest')

@section('title', 'Pesan Layanan Laundry - Laundry Coin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 lg:pb-16">
    <!-- Judul + navigasi kembali -->
    <div class="pt-2 pb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-sky-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali ke Beranda
            </a>
            <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Pesan Layanan Laundry</h1>
            <p class="text-sm text-slate-500 mt-1">Pilih satu atau beberapa layanan sekaligus, cek keranjang, lalu bayar online.</p>
        </div>

        <!-- Stepper -->
        <x-order-stepper :current="$step" />
    </div>

    <!-- Mini tutorial cara memesan -->
    @include('pelanggan.pesan.partials.tutorial')

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2">
            @if ($step === 'detail')
                @include('pelanggan.pesan.partials.detail-form')
            @else
                @include('pelanggan.pesan.partials.service-form')
            @endif
        </div>

        <aside id="keranjang" class="lg:sticky lg:top-24 scroll-mt-24">
            @include('pelanggan.pesan.partials.cart', ['readonly' => $step === 'detail'])
        </aside>
    </div>
</div>
@endsection
