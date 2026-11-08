@extends('layouts.dashboard')

@section('title', 'Riwayat Pesanan Laundry')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Riwayat & Status Pesanan
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Satu kartu untuk satu transaksi. Pesanan di outlet tampil sebagai kartu sendiri.
            </p>
        </div>

        <a
            href="{{ route('pelanggan.pesan.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs transition-colors self-start sm:self-auto"
        >
            <x-icon name="plus" class="w-4 h-4" />
            <span>Buat Pesanan Baru</span>
        </a>
    </div>

    <!-- Tab status (sama dengan halaman Akun) -->
    <x-order-status-tabs :active="$tab" route="pelanggan.orders.index" :counts="$counts" />

    @if ($cards->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                @include('pelanggan.partials.transaction-card', ['card' => $card])
            @endforeach
        </div>

        <div>
            {{ $cards->links() }}
        </div>
    @else
        <x-card padding="p-0">
            <x-empty-state
                :title="$tab === 'semua' ? 'Belum Ada Pesanan' : 'Tidak ada pesanan di tab ini'"
                :description="$tab === 'semua' ? 'Anda belum pernah membuat pesanan di Laundry Coin. Mulai cuci pakaian Anda sekarang!' : 'Pesanan akan muncul di sini sesuai statusnya.'"
            >
                <x-slot:action>
                    <a
                        href="{{ route('pelanggan.pesan.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs transition-colors"
                    >
                        <x-icon name="plus" class="w-4 h-4" />
                        <span>Buat Pesanan</span>
                    </a>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @endif
</div>
@endsection
