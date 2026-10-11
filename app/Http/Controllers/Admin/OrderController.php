<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = Order::with(['user', 'service', 'payments'])
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                   ->orWhereHas('service', fn($s) => $s->where('name', 'like', "%{$search}%"))
                   ->orWhere('id', 'like', "%{$search}%")
            ))
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest();

        $orders = $query->paginate(10)->withQueryString()->through(fn($o) => [
            'id'             => $o->id,
            'invoice'        => 'ORD-' . str_pad($o->id, 4, '0', STR_PAD_LEFT),
            'customer'       => $o->user?->name ?? '-',
            'customer_email' => $o->user?->email ?? '-',
            'service'        => $o->service?->name ?? '-',
            'service_id'     => $o->service_id,
            'weight'         => $o->weight,
            'status'         => $o->status,
            'total_price'    => $o->total_price,
            'pickup_address' => $o->pickup_address,
            'delivery_address' => $o->delivery_address,
            'payment_status' => $o->payments->first()?->status ?? 'belum',
            'payment_method' => $o->payments->first()?->method ?? '-',
            'date'           => $o->created_at->format('d M Y'),
        ]);

        // ── Stats ──────────────────────────────────────────
        $stats = [
            'total'    => Order::count(),
            'selesai'  => Order::where('status', 'selesai')->count(),
            'diproses' => Order::where('status', 'diproses')->count(),
            'pending'  => Order::where('status', 'pending')->count(),
        ];

        // ── Chart 1: Status Distribution (Doughnut) ────────
        $statusDist = [
            Order::where('status', 'selesai')->count(),
            Order::where('status', 'diproses')->count(),
            Order::where('status', 'pending')->count(),
            Order::where('status', 'diantar')->count(),
        ];

        // ── Chart 2: Weekly Trend (Line) ──────────────────
        $weeklyTrend = collect(range(6, 0))->map(function ($i) {
            $day = Carbon::now()->subDays($i);
            return [
                'label' => $day->format('D'),
                'value' => Order::whereDate('created_at', $day->toDateString())->count(),
            ];
        });

        // ── Chart 3: Payment Method (Doughnut) ────────────
        $paymentMethods = [
            Payment::where('method', 'transfer')->count(),
            Payment::where('method', 'cash')->count(),
            Payment::where('method', 'e-wallet')->count(),
        ];

        // ── Chart 4: Top services (Bar) 4 months ──────────
        $services = Service::orderByDesc(
            Order::selectRaw('COUNT(*)')
                ->whereColumn('service_id', 'services.id')
        )->limit(3)->get();

        $topServicesChart = collect(range(3, 0))->map(function ($i) use ($services) {
            $month = Carbon::now()->subMonths($i);
            $row = ['label' => $month->format('M')];
            foreach ($services as $s) {
                $row[$s->name] = Order::where('service_id', $s->id)
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();
            }
            return $row;
        });

        // Services list for form dropdown
        $serviceList = Service::select('id', 'name', 'price')->get();
        $customerList = User::where('role', 'pelanggan')->select('id', 'name', 'email')->get();

        return Inertia::render('dashboard/admin/manajemen-order', [
            'orders'      => $orders,
            'stats'       => $stats,
            'filters'     => ['search' => $search, 'status' => $status],
            'services'    => $serviceList,
            'customers'   => $customerList,
            'chartData'   => [
                'statusDist'     => $statusDist,
                'weeklyTrend'    => $weeklyTrend,
                'paymentMethods' => $paymentMethods,
                'topServices'    => $topServicesChart,
                'serviceNames'   => $services->pluck('name'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'service_id'       => 'required|exists:services,id',
            'weight'           => 'required|numeric|min:0.1',
            'status'           => ['required', Rule::in(['pending', 'diproses', 'selesai', 'diantar'])],
            'total_price'      => 'required|numeric|min:0',
            'pickup_address'   => 'required|string',
            'delivery_address' => 'required|string',
        ]);

        Order::create($validated);

        return back()->with('success', 'Pesanan berhasil dibuat.');
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'service_id'       => 'required|exists:services,id',
            'weight'           => 'required|numeric|min:0.1',
            'status'           => ['required', Rule::in(['pending', 'diproses', 'selesai', 'diantar'])],
            'total_price'      => 'required|numeric|min:0',
            'pickup_address'   => 'required|string',
            'delivery_address' => 'required|string',
        ]);

        $order->update($validated);

        return back()->with('success', 'Pesanan berhasil diperbarui.');
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return back()->with('success', 'Pesanan berhasil dihapus.');
    }
}
