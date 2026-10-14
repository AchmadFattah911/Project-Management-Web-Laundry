@extends('layouts.guest')

@section('title', 'Aturan Penggunaan - Laundry Coin')

@section('content')
@php
    $rules = [
        'Pemesanan' => [
            'Layanan Self Laundry hanya bisa digunakan langsung di outlet dan tidak dapat dipesan online.',
            'Layanan Drop-Off dapat dipesan online melalui akun pelanggan, lalu pakaian dibawa ke outlet.',
            'Total biaya dihitung otomatis oleh sistem berdasarkan tarif layanan yang berlaku saat pemesanan.',
        ],
        'Pembayaran' => [
            'Pembayaran online dilakukan secara cashless melalui metode yang tersedia (Transfer Bank atau E-Wallet / QRIS).',
            'Tagihan online memiliki batas waktu. Tagihan yang kadaluarsa atau gagal dapat dibuat ulang dari halaman detail pesanan.',
            'Pembayaran Self Laundry di outlet dilakukan langsung di kasir.',
        ],
        'Penggunaan Mesin' => [
            'Patuhi batas kapasitas mesin yang tertera di halaman layanan agar hasil cuci dan pengeringan optimal.',
            'Segera ambil pakaian setelah siklus mesin selesai agar tidak menghambat pengguna lain.',
        ],
        'Pengambilan Pakaian' => [
            'Pakaian Drop-Off dapat diambil di outlet setelah status pesanan menjadi Siap Diambil, dengan menunjukkan kode pesanan.',
        ],
    ];
@endphp

<section class="max-w-3xl mx-auto px-4 sm:px-6 py-12 space-y-6">
    <div>
        <span class="inline-flex px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-wider border border-sky-200">Tentang Kami</span>
        <h1 class="mt-3 text-3xl font-extrabold text-slate-900 tracking-tight">Aturan Penggunaan</h1>
        <p class="mt-2 text-sm text-slate-600">Ketentuan penggunaan layanan dan website {{ config('laundry.store_name', 'Laundry Coin') }}.</p>
    </div>

    @foreach ($rules as $title => $items)
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-900 mb-3">{{ $loop->iteration }}. {{ $title }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-slate-600 leading-relaxed">
                @foreach ($items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</section>
@endsection
