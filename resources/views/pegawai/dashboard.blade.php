@extends('layouts.dashboard')

@section('title', 'Antrean Pengerjaan Pegawai')

@section('content')
<div class="space-y-6">

    <!-- Top Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-sky-950 to-slate-900 rounded-2xl p-6 sm:p-8 text-white shadow-md flex flex-col md:flex-row md:items-center justify-between gap-6 border border-slate-800">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-semibold mb-2 border border-amber-400/30">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Dashboard Staf Operasional</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                Pusat Antrean Cuci & Kasir
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
                Kelola tahapan pencucian mesin, pengeringan, lipatan rapi, dan pembayaran pelanggan walk-in.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('pegawai.orders.index') }}"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Kelola Semua Pesanan</span>
            </a>
        </div>
    </div>

    <!-- 4 Stat Cards Antrean -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Antrean Cuci"
            :value="$stats['antrean_cuci'] ?? 0"
            hint="Menunggu mesin washer"
            color="sky"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Antrean Kering"
            :value="$stats['antrean_kering'] ?? 0"
            hint="Menunggu mesin dryer"
            color="amber"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Antrean Lipat"
            :value="$stats['antrean_lipat'] ?? 0"
            hint="Menunggu proses folding"
            color="violet"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card
            title="Selesai Hari Ini"
            :value="$stats['selesai_hari_ini'] ?? 0"
            hint="Tuntas diproses hari ini"
            color="emerald"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <!-- Antrean Pengerjaan Aktif Card -->
    <x-card title="Antrean Proses Pengerjaan Aktif" subtitle="Daftar cucian yang butuh segera diproses (Cuci, Kering, atau Lipat)">
        @if (($activeProcesses ?? collect())->isNotEmpty())
            <x-table :headers="['Kode Order', 'Pelanggan', 'Layanan', 'Tahap Pengerjaan', 'Status', 'Aksi Cepat']">
                @foreach ($activeProcesses as $proc)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-slate-900 text-xs">
                            {{ $proc->order?->order_code ?? '-' }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900 text-xs">
                                {{ $proc->order?->customer_name ?? $proc->order?->user?->name ?? 'Walk-in' }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ $proc->order?->customer_phone ?? $proc->order?->user?->phone ?? '-' }}
                            </div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            {{ $proc->order?->service?->name ?? 'Layanan' }}
                            @if (($proc->order?->service?->unit ?? 'Mesin') === 'Mesin')
                                <span class="text-[11px] text-slate-500">({{ $proc->order?->machine_qty }} × mesin)</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider
                                @if($proc->process === 'cuci') bg-sky-100 text-sky-800
                                @elseif($proc->process === 'kering') bg-amber-100 text-amber-800
                                @else bg-violet-100 text-violet-800 @endif
                            ">
                                {{ $proc->process }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                <span>Menunggu</span>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            @can('update', $proc->order)
                                <form action="{{ route('pegawai.orders.process', $proc) }}" method="POST" class="inline-block" onsubmit="return confirm('Tandai proses {{ strtoupper($proc->process) }} pesanan {{ $proc->order?->order_code }} sebagai SELESAI?');">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="selesai">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Tandai Selesai</span>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state
                title="Antrean Pengerjaan Bersih"
                description="Tidak ada proses cuci, kering, atau lipat yang tertunda saat ini. Kerja bagus!"
            />
        @endif
    </x-card>

    <!-- Pesanan Hari Ini Card -->
    <x-card title="Pesanan Berjalan Hari Ini" subtitle="10 pesanan pending atau dalam proses pengerjaan">
        <x-slot:action>
            <a href="{{ route('pegawai.orders.index') }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">
                Buka Kasir & Daftar Pesanan →
            </a>
        </x-slot:action>

        @if (($todayOrders ?? collect())->isNotEmpty())
            <x-table :headers="['Kode Order', 'Pelanggan', 'Layanan', 'Jumlah Mesin', 'Total Biaya', 'Status Cucian']">
                @foreach ($todayOrders as $order)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-slate-900 text-xs">
                            {{ $order->order_code }}
                        </td>
                        <td class="px-5 py-4 text-xs font-semibold text-slate-800">
                            {{ $order->customer_name ?? $order->user?->name ?? 'Walk-in' }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            {{ $order->service?->name ?? 'Layanan' }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            @if (($order->service?->unit ?? 'Mesin') === 'Mesin')
                                {{ $order->machine_qty }} × Mesin
                            @else
                                {{ $order->machine_qty }} Paket
                            @endif
                        </td>
                        <td class="px-5 py-4 text-xs font-extrabold text-slate-900">
                            Rp {{ number_format($order->total_price, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-4">
                            <x-badge :status="$order->status" size="sm" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state
                title="Tidak Ada Pesanan Berjalan"
                description="Belum ada pesanan aktif yang perlu diproses."
            />
        @endif
    </x-card>

</div>
@endsection
