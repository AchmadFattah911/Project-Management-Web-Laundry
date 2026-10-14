@extends('layouts.guest')

@section('title', 'Lacak Pesanan Laundry - Laundry Coin')
@section('meta_description', 'Lacak progres pengerjaan cucian Anda secara realtime dengan memasukkan kode unik pesanan Laundry Coin.')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)]">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-100 text-sky-800 text-xs font-bold uppercase tracking-wider mb-2">
                Pelacakan Publik
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Lacak Status Pesanan Cucian
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-2">
                Pantau proses cucian Anda langsung dari sistem mesin laundromat kami.
            </p>
        </div>

        <!-- Search Card -->
        <x-card class="mb-8">
            <form method="GET" action="{{ route('tracking') }}" class="space-y-4">
                <div>
                    <label for="order_code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Kode Pesanan
                    </label>
                    <div class="relative rounded-lg shadow-xs flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <x-icon name="search" class="w-5 h-5" />
                            </span>
                            <input
                                id="order_code"
                                type="text"
                                name="order_code"
                                value="{{ $orderCode ?? '' }}"
                                placeholder="Contoh: LC-XXXXXX"
                                required
                                autofocus
                                class="w-full pl-11 pr-4 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono text-sm uppercase"
                            />
                        </div>
                        <x-button type="submit" variant="primary" size="md" class="shrink-0">
                            <span>Lacak Sekarang</span>
                        </x-button>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        Kode pesanan tertera pada struk kasir atau pesan konfirmasi Anda.
                    </p>
                </div>
            </form>
        </x-card>

        <!-- Result / Empty States -->
        @if (!empty($trackedOrder))
            @php
                $statusBadgeVariant = match($trackedOrder['status']) {
                    'pending'  => 'warning',
                    'diproses' => 'info',
                    'siap_diambil' => 'success',
                    'selesai'  => 'neutral',
                    default    => 'neutral',
                };
            @endphp
            <x-card title="Informasi Pesanan" :subtitle="'Kode Unik: ' . $trackedOrder['order_code']">
                <div class="space-y-6">
                    <!-- Status Highlight -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-sky-50/70 border border-sky-100">
                        <div>
                            <span class="text-xs text-sky-800 font-semibold uppercase tracking-wider">Status Saat Ini</span>
                            <div class="flex items-center gap-2 mt-1">
                                <x-badge :variant="$statusBadgeVariant" size="md">
                                    {{ ucfirst($trackedOrder['status']) }}
                                </x-badge>
                                <span class="text-xs text-slate-600">
                                    @if ($trackedOrder['status'] === 'pending')
                                        Menunggu antrean mesin.
                                    @elseif ($trackedOrder['status'] === 'diproses')
                                        Sedang dalam tahap pengerjaan.
                                    @elseif ($trackedOrder['status'] === 'siap_diambil')
                                        Cucian bersih & siap diambil di outlet.
                                    @elseif ($trackedOrder['status'] === 'selesai')
                                        Pesanan selesai dan sudah diambil oleh pelanggan.
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-xs text-slate-500">Waktu Order Masuk</span>
                            <p class="text-xs font-semibold text-slate-800 mt-0.5">{{ $trackedOrder['created_at'] }}</p>
                        </div>
                    </div>

                    <!-- Details Grid (Minimal Info, NO PII / Tanpa Nama & HP) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 rounded-lg border border-slate-100 bg-slate-50/60">
                            <p class="text-slate-500 font-medium">Layanan Laundry</p>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ $trackedOrder['service'] }}</p>
                        </div>
                        <div class="p-3.5 rounded-lg border border-slate-100 bg-slate-50/60">
                            <p class="text-slate-500 font-medium">Jumlah Mesin Digunakan</p>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ $trackedOrder['machine_qty'] }} unit mesin</p>
                        </div>
                    </div>

                    <!-- Privacy Notice -->
                    <div class="flex items-center gap-2 text-[11px] text-slate-500 border-t border-slate-100 pt-3">
                        <x-icon name="check" class="w-4 h-4 text-emerald-600 shrink-0" />
                        <span>Data pribadi (nama & nomor HP) disembunyikan untuk menjaga privasi pelanggan.</span>
                    </div>
                </div>
            </x-card>
        @elseif (!empty($notFound))
            <!-- State Kode Tidak Ditemukan -->
            <x-card class="text-center py-8">
                <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3">
                    <x-icon name="x" class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-slate-900">Kode Pesanan Tidak Ditemukan</h3>
                <p class="text-xs text-slate-600 max-w-md mx-auto mt-1 mb-6">
                    Pesanan dengan kode <span class="font-mono font-bold text-rose-600">{{ $orderCode }}</span> tidak tercatat di database kami. Pastikan huruf besar/kecil dan angka sesuai dengan struk cucian Anda.
                </p>
                <x-button href="{{ route('tracking') }}" variant="outline" size="sm">
                    Coba Kode Lain
                </x-button>
            </x-card>
        @else
            <!-- Default Empty State (Sebelum Mencari) -->
            <x-empty-state
                icon="search"
                title="Masukkan Kode Pesanan"
                description="Ketik kode unik pesanan Anda pada formulir di atas untuk melihat progres cucian."
            />
        @endif
    </div>
</div>
@endsection
