@extends('layouts.mobile', ['bottomNavActive' => 'transaksi'])

@section('title', 'Riwayat Pemesanan')

@section('content')
@include('pelanggan.partials.page-header', ['title' => 'Riwayat Pemesanan', 'back' => route('pelanggan.akun')])

<div class="px-4 py-4 space-y-4">
    <!-- Tab status (sama dengan halaman Akun) -->
    <x-order-status-tabs :active="$tab" route="pelanggan.orders.index" :counts="$counts" />

    @if ($cards->isNotEmpty())
        <div class="space-y-3">
            @foreach ($cards as $card)
                @include('pelanggan.partials.transaction-card', ['card' => $card])
            @endforeach
        </div>

        <div>
            {{ $cards->links() }}
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200 py-10 px-4 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                <x-icon name="package" class="w-6 h-6" />
            </div>
            <p class="text-sm font-bold text-slate-700">{{ $tab === 'semua' ? 'Belum Ada Pesanan' : 'Tidak ada pesanan di tab ini' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $tab === 'semua' ? 'Anda belum pernah membuat pesanan di Laundry Coin.' : 'Pesanan akan muncul di sini sesuai statusnya.' }}</p>
            <a href="{{ route('pelanggan.pesan.index') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-colors">
                <x-icon name="plus" class="w-4 h-4" />
                <span>Buat Pesanan</span>
            </a>
        </div>
    @endif
</div>
@endsection
