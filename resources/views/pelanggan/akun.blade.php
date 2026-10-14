@extends('layouts.mobile', ['bottomNavActive' => 'akun'])

@section('title', 'Akun Saya')

@section('content')
@php
    $tabTitles = [
        'semua'    => 'Belum ada pesanan',
        'diproses' => 'Tidak ada pesanan yang sedang diproses',
        'selesai'  => 'Belum ada pesanan yang selesai',
    ];
@endphp

<!-- 1. Header & Profil (latar biru) -->
<header class="bg-gradient-to-br from-sky-600 to-sky-700 text-white px-4 pt-4 pb-6 rounded-b-3xl">
    <!-- Top bar: judul kiri, ikon notifikasi / pesan / pengaturan di kanan -->
    <div class="flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-sm font-extrabold tracking-tight">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Akun</span>
        </a>

        <div class="flex items-center gap-1">
            {{-- Notifikasi: pesanan yang sedang berjalan --}}
            <a href="{{ route('pelanggan.akun', ['status' => 'diproses']) }}#riwayat" class="relative p-2 rounded-full hover:bg-white/15 transition-colors" aria-label="Notifikasi">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                @if ($counts['diproses'] > 0)
                    <span class="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-amber-400 text-slate-900 text-[10px] font-extrabold leading-4 text-center">{{ $counts['diproses'] }}</span>
                @endif
            </a>
            {{-- Pesan: kontak outlet --}}
            <a href="{{ route('home') }}#kontak" class="p-2 rounded-full hover:bg-white/15 transition-colors" aria-label="Pesan">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
            </a>
            {{-- Pengaturan akun --}}
            <a href="{{ route('profile.edit') }}" class="p-2 rounded-full hover:bg-white/15 transition-colors" aria-label="Pengaturan">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </a>
        </div>
    </div>

    <!-- Profil rata kanan: nama + ikon pensil, lalu foto profil bulat di paling kanan -->
    <div class="mt-5 flex items-center justify-end gap-3">
        <div class="min-w-0 text-right">
            <div class="flex items-center justify-end gap-1.5">
                <h1 class="text-base sm:text-lg font-extrabold truncate">{{ $user->name }}</h1>
                <a href="{{ route('profile.edit') }}" class="p-1 rounded-full hover:bg-white/15 transition-colors shrink-0" aria-label="Edit profil">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </a>
            </div>
            <p class="text-xs text-sky-100 truncate">{{ $user->phone ?: $user->email }}</p>
        </div>
        <div class="w-16 h-16 rounded-full bg-white text-sky-700 flex items-center justify-center text-xl font-extrabold ring-4 ring-white/30 shrink-0" aria-hidden="true">
            {{ $user->initials() }}
        </div>
    </div>
</header>

<div class="px-4 -mt-2 space-y-4">
    <!-- 3. Riwayat Pemesanan -->
    <section id="riwayat" class="bg-white rounded-2xl border border-slate-200 p-4 scroll-mt-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold text-slate-900">Riwayat Pemesanan</h2>
            <a href="{{ route('pelanggan.orders.index', $tab === 'semua' ? [] : ['status' => $tab]) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">
                Lihat Semua
            </a>
        </div>

        <x-order-status-tabs :active="$tab" route="pelanggan.akun" :counts="$counts" fragment="riwayat" class="mb-3" />

        @if ($orders->isEmpty())
            <div class="py-8 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                    <x-icon name="package" class="w-6 h-6" />
                </div>
                <p class="text-sm font-bold text-slate-700">{{ $tabTitles[$tab] }}</p>
                @if ($tab === 'semua')
                    <a href="{{ route('pelanggan.pesan.index') }}" class="mt-3 inline-flex px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-colors">
                        Pesan Layanan Sekarang
                    </a>
                @endif
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($orders as $order)
                    @php
                        $payment = $order->paymentForDisplay();
                        $payStatus = $payment?->displayStatus() ?? 'pending';
                    @endphp
                    <li>
                        <a href="{{ route('pelanggan.orders.show', $order) }}" class="flex items-start gap-3 py-3 group">
                            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                                <x-icon name="washer" class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-bold text-slate-900 truncate group-hover:text-sky-700">{{ $order->service?->name ?? 'Layanan' }}</p>
                                    <x-badge :status="$order->status" size="sm" />
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    <span class="font-mono font-semibold">{{ $order->order_code }}</span> &middot; {{ $order->created_at->format('d M Y, H:i') }}
                                </p>
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <p class="text-sm font-extrabold text-slate-900">Rp {{ number_format($order->total_price, 0, ',', '.') }}</p>
                                    <x-badge :payment="$payStatus" size="sm" />
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <!-- 4. Tentang Kami (accordion) -->
    <section x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <button
            type="button"
            x-on:click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="tentang-kami-menu"
            class="w-full flex items-center justify-between px-4 py-3.5 text-sm font-extrabold text-slate-900 hover:bg-slate-50 transition-colors"
        >
            <span>Tentang Kami</span>
            <svg class="w-5 h-5 text-slate-400 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <ul id="tentang-kami-menu" x-show="open" x-collapse x-cloak class="border-t border-slate-100 divide-y divide-slate-100">
            @foreach ([
                ['label' => 'Tentang Laundry Coin', 'href' => route('tentang')],
                ['label' => 'Hubungi Kami', 'href' => route('home') . '#kontak'],
                ['label' => 'Aturan Penggunaan', 'href' => route('aturan')],
            ] as $menu)
                <li>
                    <a href="{{ $menu['href'] }}" class="flex items-center justify-between px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 hover:text-sky-700 transition-colors">
                        <span>{{ $menu['label'] }}</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <!-- Keluar akun -->
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full py-3 rounded-2xl border border-rose-200 bg-white text-sm font-bold text-rose-600 hover:bg-rose-50 transition-colors">
            Keluar
        </button>
    </form>
</div>
@endsection
