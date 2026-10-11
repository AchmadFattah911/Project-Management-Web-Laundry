<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search   = $request->input('search', '');
        $category = $request->input('category', '');

        $query = Service::withCount('orders')
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('category', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($category, fn($q) => $q->where('category', $category))
            ->latest();

        $services = $query->paginate(10)->withQueryString()->through(fn($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'category'    => $s->category,
            'price'       => $s->price,
            'estimate'    => $s->estimate,
            'status'      => $s->status,
            'description' => $s->description,
            'orders_count'=> $s->orders_count,
        ]);

        // ── Stats ─────────────────────────────────────────────
        $totalServices  = Service::count();
        $available      = Service::where('status', 'tersedia')->count();
        $categories     = Service::distinct()->pluck('category');
        $categoryGroups = Service::selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->get()->pluck('total', 'category');

        $stats = [
            'total'     => $totalServices,
            'tersedia'  => $available,
            'sibuk'     => Service::where('status', 'sibuk')->count(),
            'nonaktif'  => Service::where('status', 'tidak_tersedia')->count(),
        ];

        // ── Chart 1: Distribusi Kategori (Doughnut) ────────────
        $allCategories = Service::selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->get();

        // ── Chart 2: Trend Pemesanan per Kategori (Line) 6 bln ─
        $trendMonths = collect(range(5, 0))->map(function ($i) {
            $month = Carbon::now()->subMonths($i);
            $kiloan = Order::whereHas('service', fn($q) => $q->where('category', 'Kiloan'))
                ->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count();
            $satuan = Order::whereHas('service', fn($q) => $q->where('category', 'Satuan'))
                ->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count();
            return ['label' => $month->format('M'), 'kiloan' => $kiloan, 'satuan' => $satuan];
        });

        // ── Chart 3: Status Ketersediaan (Doughnut) ────────────
        $statusDist = [$available, $stats['sibuk'], $stats['nonaktif']];

        // ── Chart 4: Harga per Layanan (Bar) top 5 ─────────────
        $priceChart = Service::orderBy('price', 'desc')->limit(6)->get()
            ->map(fn($s) => ['name' => $s->name, 'price' => (float)$s->price]);

        // ── Available categories for filter dropdown ────────────
        $categoryList = Service::distinct()->orderBy('category')->pluck('category');

        return Inertia::render('dashboard/admin/layanan-laundry', [
            'services'     => $services,
            'stats'        => $stats,
            'filters'      => ['search' => $search, 'category' => $category],
            'categoryList' => $categoryList,
            'chartData'    => [
                'categories' => $allCategories,
                'trendMonths'=> $trendMonths,
                'statusDist' => $statusDist,
                'priceChart' => $priceChart,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'required|string|max:100',
            'price'       => 'required|numeric|min:0',
            'estimate'    => 'required|string|max:100',
            'status'      => ['required', Rule::in(['tersedia', 'sibuk', 'tidak_tersedia'])],
            'description' => 'nullable|string',
        ]);

        Service::create($validated);

        return back()->with('success', 'Layanan berhasil ditambahkan.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'required|string|max:100',
            'price'       => 'required|numeric|min:0',
            'estimate'    => 'required|string|max:100',
            'status'      => ['required', Rule::in(['tersedia', 'sibuk', 'tidak_tersedia'])],
            'description' => 'nullable|string',
        ]);

        $service->update($validated);

        return back()->with('success', 'Layanan berhasil diperbarui.');
    }

    public function destroy(Service $service)
    {
        if ($service->orders()->exists()) {
            return back()->with('error', 'Layanan tidak dapat dihapus karena masih memiliki pesanan terkait.');
        }

        $service->delete();

        return back()->with('success', 'Layanan berhasil dihapus.');
    }
}
