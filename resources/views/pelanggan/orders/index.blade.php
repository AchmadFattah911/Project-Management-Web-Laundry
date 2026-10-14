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
                Semua daftar cucian yang pernah atau sedang Anda proses di Laundry Coin
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

    <!-- Filter Status (sama dengan tab di halaman Akun) -->
    <x-order-status-tabs :active="$tab" route="pelanggan.orders.index" />

    <!-- Orders Table Card -->
    <x-card padding="p-0">
        @if ($orders->isNotEmpty())
            <x-table :headers="['Kode Order', 'Tanggal', 'Layanan', 'Jumlah Mesin', 'Total Biaya', 'Status Cucian', 'Pembayaran', 'Aksi']">
                @foreach ($orders as $order)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-slate-900 text-xs">
                            {{ $order->order_code }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-500">
                            {{ $order->created_at->format('d M Y, H:i') }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900 text-xs">{{ $order->service?->name ?? 'Layanan' }}</div>
                            <div class="text-[11px] text-slate-500">{{ $order->service?->category ?? 'Laundromat' }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            @if (($order->service?->unit ?? 'Mesin') === 'Mesin')
                                <span class="font-bold text-slate-800">{{ $order->machine_qty }}</span> × Mesin
                            @else
                                <span class="font-bold text-slate-800">{{ $order->machine_qty }}</span> Paket
                            @endif
                        </td>
                        <td class="px-5 py-4 text-xs font-extrabold text-slate-900">
                            Rp {{ number_format($order->total_price, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-4">
                            <x-badge :status="$order->status" size="sm" />
                        </td>
                        <td class="px-5 py-4">
                            @php
                                $payment = $order->paymentForDisplay();
                                $payStatus = $payment?->displayStatus() ?? 'pending';
                            @endphp
                            <x-badge :payment="$payStatus" size="sm" />
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a
                                href="{{ route('pelanggan.orders.show', $order) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs transition-colors"
                            >
                                <span>Detail</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="p-4 border-t border-slate-200/80">
                {{ $orders->links() }}
            </div>
        @else
            <x-empty-state
                title="Belum Ada Pesanan"
                description="Anda belum pernah membuat pesanan di Laundry Coin. Mulai cuci pakaian Anda sekarang!"
            >
                <x-slot:action>
                    <a
                        href="{{ route('pelanggan.pesan.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs transition-colors"
                    >
                        <x-icon name="plus" class="w-4 h-4" />
                        <span>Buat Pesanan Pertama</span>
                    </a>
                </x-slot:action>
            </x-empty-state>
        @endif
    </x-card>
</div>
@endsection
