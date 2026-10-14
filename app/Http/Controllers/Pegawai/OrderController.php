<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = Order::with(['user', 'service', 'payments', 'checkout.payments', 'orderProcesses'])
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('order_code', 'like', "%{$search}%")
                   ->orWhere('customer_name', 'like', "%{$search}%")
                   ->orWhere('customer_phone', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest();

        $orders = $query->paginate(10)->withQueryString();

        $services = Service::aktif()->get();
        $customers = User::where('role', 'pelanggan')->select('id', 'name', 'phone')->get();

        return view('pegawai.orders.index', compact('orders', 'services', 'customers', 'search', 'status'));
    }

    /**
     * Pegawai membuat pesanan langsung (Walk-in di kasir atau atas nama pelanggan)
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orderService->create($request->validated());

            // Jika ada metode bayar cash/transfer langsung dicatat di kasir
            if ($request->filled('payment_method')) {
                app(PaymentService::class)->recordWalkIn(
                    $order,
                    $request->input('payment_method'),
                    $request->boolean('is_paid'),
                    $request->user(),
                );
            }

            return back()->with('success', "Pesanan walk-in berhasil dibuat dengan kode {$order->order_code}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Pegawai mengubah jumlah mesin atau status pesanan
     */
    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'machine_qty' => 'nullable|integer|min:1|max:20',
            'status'      => ['nullable', 'string', Rule::in(Order::STATUSES)],
        ]);

        try {
            if (isset($validated['machine_qty']) && $validated['machine_qty'] != $order->machine_qty) {
                $this->orderService->updateMachineQty($order, (int) $validated['machine_qty'], auth()->user());
            }

            if (isset($validated['status']) && $validated['status'] != $order->status) {
                $this->orderService->updateStatus($order, $validated['status'], auth()->user());
            }

            return back()->with('success', 'Data pesanan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pegawai mengupdate status tahapan pengerjaan (cuci, kering, lipat)
     */
    public function updateProcess(Request $request, OrderProcess $process)
    {
        $this->authorize('update', $process->order);

        $validated = $request->validate([
            'status' => 'required|in:pending,selesai',
        ]);

        try {
            $this->orderService->updateProcess($process, $validated['status'], auth()->user());
            return back()->with('success', "Proses {$process->process} berhasil diperbarui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pegawai mengonfirmasi pelunasan pembayaran
     */
    public function confirmPayment(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'method' => 'required|in:cash,transfer,e-wallet',
        ]);

        if ($order->isPaid()) {
            return back()->with('error', 'Pesanan ini sudah lunas sebelumnya.');
        }

        app(PaymentService::class)->confirmManually($order, $validated['method'], $request->user());

        return back()->with('success', 'Pembayaran pesanan berhasil dikonfirmasi lunas.');
    }
}
