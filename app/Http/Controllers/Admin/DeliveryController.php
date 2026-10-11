<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $type   = $request->input('type', '');

        $query = Delivery::with(['order.user', 'courier'])
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->whereHas('order.user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                   ->orWhereHas('courier', fn($c) => $c->where('name', 'like', "%{$search}%"))
            ))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($type,   fn($q) => $q->where('type', $type))
            ->latest();

        $deliveries = $query->paginate(10)->withQueryString()->through(fn($d) => [
            'id'            => $d->id,
            'task_no'       => 'TSK-' . str_pad($d->id, 4, '0', STR_PAD_LEFT),
            'order_id'      => $d->order_id,
            'customer'      => $d->order?->user?->name ?? '-',
            'address'       => $d->type === 'pickup'
                                ? ($d->order?->pickup_address ?? '-')
                                : ($d->order?->delivery_address ?? '-'),
            'courier_id'    => $d->courier_id,
            'courier'       => $d->courier?->name ?? null,
            'type'          => $d->type,
            'status'        => $d->status,
            'scheduled_at'  => $d->scheduled_at?->format('d M Y, H:i'),
            'scheduled_at_raw' => $d->scheduled_at?->format('Y-m-d\TH:i'),
            'notes'         => $d->notes,
        ]);

        // ── Stats ─────────────────────────────────────────────
        $today = Carbon::today();
        $stats = [
            'menunggu'    => Delivery::where('status', 'dijemput')->whereNull('courier_id')->count(),
            'diperjalanan'=> Delivery::whereIn('status', ['dijemput', 'diantar'])->whereNotNull('courier_id')->count(),
            'selesai_hari_ini' => Delivery::where('status', 'selesai')->whereDate('updated_at', $today)->count(),
            'kurir_aktif' => User::where('role', 'kurir')->count(),
        ];

        // ── Chart 1: Status Tugas (Doughnut) ──────────────────
        $statusDist = [
            Delivery::where('status', 'selesai')->count(),
            Delivery::whereIn('status', ['dijemput', 'diantar'])->whereNotNull('courier_id')->count(),
            Delivery::whereNull('courier_id')->count(),
        ];

        // ── Chart 2: Volume tugas 7 hari (Line) ───────────────
        $weeklyVolume = collect(range(6, 0))->map(function ($i) {
            $day = Carbon::now()->subDays($i);
            return [
                'label' => $day->locale('id')->isoFormat('ddd'),
                'value' => Delivery::whereDate('created_at', $day->toDateString())->count(),
            ];
        });

        // ── Chart 3: Tepat waktu (Doughnut) — sederhana ───────
        $selesai   = Delivery::where('status', 'selesai')->count();
        $belumSelesai = Delivery::where('status', '!=', 'selesai')->count();
        $timeDist  = [$selesai, $belumSelesai];

        // ── Chart 4: Beban kurir (Bar) ────────────────────────
        $couriers = User::where('role', 'kurir')
            ->withCount([
                'deliveries as selesai_count' => fn($q) => $q->where('status', 'selesai'),
                'deliveries as jalan_count'   => fn($q) => $q->whereIn('status', ['dijemput', 'diantar']),
            ])->get();

        // Orders available for dropdown (no delivery yet or can add more)
        $orderList = Order::with('user')->latest()->limit(100)->get()
            ->map(fn($o) => ['id' => $o->id, 'label' => 'ORD-' . str_pad($o->id, 4, '0', STR_PAD_LEFT) . ' — ' . ($o->user?->name ?? '-')]);

        $courierList = User::where('role', 'kurir')->select('id', 'name')->get();

        return Inertia::render('dashboard/admin/pickup', [
            'deliveries'  => $deliveries,
            'stats'       => $stats,
            'filters'     => ['search' => $search, 'status' => $status, 'type' => $type],
            'orderList'   => $orderList,
            'courierList' => $courierList,
            'chartData'   => [
                'statusDist'   => $statusDist,
                'weeklyVolume' => $weeklyVolume,
                'timeDist'     => $timeDist,
                'couriers'     => $couriers->map(fn($c) => [
                    'name'    => $c->name,
                    'selesai' => $c->selesai_count,
                    'jalan'   => $c->jalan_count,
                ]),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id'     => 'required|exists:orders,id',
            'courier_id'   => 'nullable|exists:users,id',
            'type'         => ['required', Rule::in(['pickup', 'delivery'])],
            'status'       => ['required', Rule::in(['dijemput', 'diantar', 'selesai'])],
            'scheduled_at' => 'nullable|date',
            'notes'        => 'nullable|string',
        ]);

        Delivery::create($validated);

        return back()->with('success', 'Tugas logistik berhasil dibuat.');
    }

    public function update(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'order_id'     => 'required|exists:orders,id',
            'courier_id'   => 'nullable|exists:users,id',
            'type'         => ['required', Rule::in(['pickup', 'delivery'])],
            'status'       => ['required', Rule::in(['dijemput', 'diantar', 'selesai'])],
            'scheduled_at' => 'nullable|date',
            'notes'        => 'nullable|string',
        ]);

        $delivery->update($validated);

        return back()->with('success', 'Tugas logistik berhasil diperbarui.');
    }

    public function destroy(Delivery $delivery)
    {
        $delivery->delete();
        return back()->with('success', 'Tugas logistik berhasil dihapus.');
    }
}
