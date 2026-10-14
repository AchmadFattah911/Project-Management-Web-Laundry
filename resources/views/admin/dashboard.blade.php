@extends('layouts.dashboard')

@section('title', 'Dashboard Overview Admin')

@section('content')
<div class="space-y-6">

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-sky-950 to-slate-900 rounded-2xl p-6 sm:p-8 text-white shadow-md flex flex-col md:flex-row md:items-center justify-between gap-6 border border-slate-800">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-500/20 text-sky-300 text-xs font-semibold mb-2 border border-sky-400/30">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Pusat Kendali Administrator</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                Ringkasan Bisnis Laundromat
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
                Pantau performa harian outlet, metrik pesanan aktif, total pemasukan kasir terverifikasi, dan pertumbuhan pelanggan.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a
                href="{{ route('admin.orders.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Kelola Pesanan</span>
            </a>
            <a
                href="{{ route('admin.services.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs shadow-xs transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Tarif Layanan</span>
            </a>
        </div>
    </div>

    <!-- 4 Kartu KPI Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Order -->
        <x-stat-card
            title="Total Pesanan"
            :value="number_format($stats['totalOrders'] ?? 0, 0, ',', '.')"
            hint="Seluruh riwayat cucian"
            color="sky"
            :trend="isset($stats['orderTrend']) ? ($stats['orderTrend'] >= 0 ? '+' : '') . $stats['orderTrend'] . '%' : null"
            :trendUp="($stats['orderTrend'] ?? 0) >= 0"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- 2. Order Aktif -->
        <x-stat-card
            title="Pesanan Aktif"
            :value="number_format($stats['activeOrders'] ?? 0, 0, ',', '.')"
            hint="Pending & sedang diproses"
            color="amber"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- 3. Pendapatan (Hanya Payments Paid) -->
        <x-stat-card
            title="Total Pendapatan (Lunas)"
            :value="'Rp ' . number_format($stats['revenue'] ?? 0, 0, ',', '.')"
            hint="Pembayaran berstatus paid"
            color="emerald"
            :trend="isset($stats['revenueTrend']) ? ($stats['revenueTrend'] >= 0 ? '+' : '') . $stats['revenueTrend'] . '%' : null"
            :trendUp="($stats['revenueTrend'] ?? 0) >= 0"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <!-- 4. Jumlah Pelanggan -->
        <x-stat-card
            title="Jumlah Pelanggan"
            :value="number_format($stats['totalUsers'] ?? 0, 0, ',', '.')"
            hint="Member pelanggan terdaftar"
            color="violet"
            :trend="isset($stats['userTrend']) ? ($stats['userTrend'] >= 0 ? '+' : '') . $stats['userTrend'] . '%' : null"
            :trendUp="($stats['userTrend'] ?? 0) >= 0"
        >
            <x-slot:icon>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <!-- Grafik Tren Pesanan 6 Bulan Terakhir -->
    <x-card title="Tren Pesanan 6 Bulan Terakhir" subtitle="Volume order per bulan untuk analisis kapasitas outlet">
        <x-slot:action>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                <span>Chart.js Realtime Feed</span>
            </span>
        </x-slot:action>

        <div class="h-64 sm:h-72 w-full pt-2">
            <canvas id="orderChartCanvas"></canvas>
        </div>
    </x-card>

    <!-- Tabel Pesanan Terbaru -->
    <x-card title="Pesanan Terbaru" subtitle="5 pesanan laundry paling mutakhir yang masuk ke sistem">
        <x-slot:action>
            <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-sky-600 hover:text-sky-700 inline-flex items-center gap-1">
                <span>Buka Semua Pesanan</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </x-slot:action>

        @if (($recentOrders ?? collect())->isNotEmpty())
            <x-table :headers="['Kode Order', 'Waktu', 'Pelanggan', 'Layanan', 'Total Biaya', 'Status Cucian', 'Pembayaran']">
                @foreach ($recentOrders as $order)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-slate-900 text-xs">
                            {{ $order->order_code }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-500">
                            {{ $order->created_at->format('d M, H:i') }}
                        </td>
                        <td class="px-5 py-4 text-xs font-semibold text-slate-800">
                            {{ $order->customer_name ?? $order->user?->name ?? 'Walk-in' }}
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            {{ $order->service?->name ?? 'Layanan' }}
                            @if (($order->service?->unit ?? 'Mesin') === 'Mesin')
                                <span class="text-[11px] text-slate-500">({{ $order->machine_qty }} × mesin)</span>
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
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state
                title="Belum Ada Pesanan"
                description="Belum ada transaksi cucian yang tercatat pada sistem."
            />
        @endif
    </x-card>

</div>
@endsection

@push('scripts')
<script>
    window.initChart = function () {
        const canvas = document.getElementById('orderChartCanvas');
        if (!canvas) return;

        const chartData = @js($orderChart ?? []);
        const labels = chartData.map(item => item.label);
        const dataValues = chartData.map(item => item.value);

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pesanan Selesai / Masuk',
                    data: dataValues,
                    backgroundColor: 'rgba(2, 132, 199, 0.85)',
                    hoverBackgroundColor: 'rgba(3, 105, 161, 1)',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return context.raw + ' pesanan laundry';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            font: { size: 11 }
                        },
                        grid: {
                            color: 'rgba(226, 232, 240, 0.8)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initChart);
    } else {
        window.initChart();
    }
</script>
@endpush
