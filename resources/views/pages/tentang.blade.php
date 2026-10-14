@extends('layouts.guest')

@section('title', 'Tentang Laundry Coin')

@section('content')
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-12 space-y-6">
    <div>
        <span class="inline-flex px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-wider border border-sky-200">Tentang Kami</span>
        <h1 class="mt-3 text-3xl font-extrabold text-slate-900 tracking-tight">Tentang Laundry Coin</h1>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4 text-sm text-slate-600 leading-relaxed">
        <p>
            <strong class="text-slate-900">{{ config('laundry.store_name', 'Laundry Coin') }}</strong> adalah laundromat modern di
            {{ config('laundry.city') }} dengan mesin cuci dan pengering berkapasitas besar yang higienis dan praktis.
        </p>
        <p>Kami menyediakan dua jenis layanan:</p>
        <ul class="list-disc list-inside space-y-1">
            <li><strong class="text-slate-900">Self Laundry</strong> - cuci dan keringkan pakaian sendiri, khusus langsung di outlet.</li>
            <li><strong class="text-slate-900">Drop-Off</strong> - pakaian dikerjakan oleh petugas kami (cuci, kering, lipat), bisa dipesan dan dibayar online secara cashless.</li>
        </ul>
        <p>Status setiap pesanan Drop-Off bisa dipantau dari halaman Akun atau menu Lacak Pesanan.</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('home') }}#layanan" class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-colors">Lihat Layanan</a>
        <a href="{{ route('home') }}#kontak" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors">Hubungi Kami</a>
    </div>
</section>
@endsection
