<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminDashboardController extends Controller
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

        // ── 2. Total Pengguna (role pelanggan) ──────────
        $totalUsers = User::where('role', 'pelanggan')->count();
        $lastMonthUsers = User::where('role', 'pelanggan')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $thisMonthUsers = User::where('role', 'pelanggan')
            ->where('created_at', '>=', $thisMonth)->count();
        $userTrend = $lastMonthUsers > 0
            ? round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100)
            : null;

        // ── 3. Pesanan Hari Ini ──────────────────────────
        $todayOrders = Order::whereDate('created_at', $today)->count();

        // ── 4. Pendapatan Bulan Ini (payments paid) ──────
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
        $recentOrders = Order::with(['user', 'service'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($o) => [
                'id'           => $o->id,
                'invoice'      => 'INV-' . str_pad($o->id, 4, '0', STR_PAD_LEFT),
                'customer'     => $o->user?->name ?? 'Pelanggan',
                'service'      => $o->service?->name ?? '-',
                'status'       => $o->status,
                'total_price'  => $o->total_price,
                'created_at'   => $o->created_at->format('d M Y, H:i'),
            ]);

        // ── 6. Tren order per 6 bulan (bar chart) ────────
        $months = collect(range(5, 0))->map(function ($i) {
            $month = Carbon::now()->subMonths($i);
            $count = Order::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            return [
                'label' => $month->format('M'),
                'value' => $count,
            ];
        });

        return Inertia::render('dashboard/admin/admin', [
            'stats' => [
                'totalOrders'  => $totalOrders,
                'orderTrend'   => $orderTrend,
                'totalUsers'   => $totalUsers,
                'userTrend'    => $userTrend,
                'todayOrders'  => $todayOrders,
                'revenue'      => $thisMonthRevenue,
                'revenueTrend' => $revenueTrend,
            ],
            'recentOrders' => $recentOrders,
            'orderChart'   => $months,
        ]);
    }
}
