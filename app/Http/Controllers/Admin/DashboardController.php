<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // ── 1. Total Pesanan ─────────────────────────────
        $totalOrders = Order::count();
        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $thisMonthOrders = Order::where('created_at', '>=', $thisMonth)->count();
        $orderTrend = $lastMonthOrders > 0
            ? round((($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100)
            : null;

        // ── 2. Total Pelanggan Terdaftar ─────────────────
        $totalUsers = User::where('role', UserRole::PELANGGAN->value)->count();
        $lastMonthUsers = User::where('role', UserRole::PELANGGAN->value)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $thisMonthUsers = User::where('role', UserRole::PELANGGAN->value)
            ->where('created_at', '>=', $thisMonth)->count();
        $userTrend = $lastMonthUsers > 0
            ? round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100)
            : null;

        // ── 3. Pesanan Aktif (pending & diproses) ──────
        $activeOrders = Order::whereIn('status', ['pending', 'diproses'])->count();
        $todayOrders = Order::whereDate('created_at', $today)->count();

        // ── 4. Pendapatan (hanya dari payments berstatus paid) ──
        $totalRevenue = Payment::where('status', 'paid')->sum('amount');
        $thisMonthRevenue = Payment::where('status', 'paid')
            ->where('paid_at', '>=', $thisMonth)
            ->sum('amount');
        $lastMonthRevenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonth, $lastMonthEnd])
            ->sum('amount');
        $revenueTrend = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : null;

        // ── 5. Pesanan Terbaru (5 terakhir) ─────────────
        $recentOrders = Order::with(['user', 'service', 'payments', 'checkout.payments'])
            ->latest()
            ->limit(5)
            ->get();

        // ── 6. Tren order per 6 bulan untuk grafik ───────
        $orderChart = collect(range(5, 0))->map(function ($i) {
            $month = Carbon::now()->subMonths($i);
            $count = Order::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            return [
                'label' => $month->translatedFormat('M Y'),
                'value' => $count,
            ];
        });

        $stats = [
            'totalOrders'      => $totalOrders,
            'activeOrders'     => $activeOrders,
            'orderTrend'       => $orderTrend,
            'totalUsers'       => $totalUsers,
            'userTrend'        => $userTrend,
            'todayOrders'      => $todayOrders,
            'revenue'          => $totalRevenue,
            'thisMonthRevenue' => $thisMonthRevenue,
            'revenueTrend'     => $revenueTrend,
        ];

        return view('admin.dashboard', compact('stats', 'recentOrders', 'orderChart'));
    }
}
