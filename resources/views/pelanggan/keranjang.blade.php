@extends('layouts.guest')

@section('title', 'Keranjang Layanan - Laundry Coin')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 lg:pb-16">
    <!-- Judul + navigasi kembali -->
    <div class="pt-2 pb-6">
        <a href="{{ route('pelanggan.pesan.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-sky-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Kembali pilih layanan
        </a>
        <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Keranjang Layanan</h1>
        <p class="text-sm text-slate-500 mt-1">Cek layanan yang sudah kamu pilih. Ubah jumlah mesin atau hapus sebelum lanjut ke detail pemesanan.</p>
    </div>

    <div id="keranjang">
        @include('pelanggan.pesan.partials.cart', ['readonly' => false])
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('pelanggan.pesan.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 hover:text-sky-800">
            <x-icon name="plus" class="w-4 h-4" /> Tambah layanan lain
        </a>
    </div>
</div>
@endsection
