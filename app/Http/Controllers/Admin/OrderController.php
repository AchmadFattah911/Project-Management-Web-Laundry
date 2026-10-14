<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
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
        $paymentStatus = $request->input('payment_status', '');
        $date = $request->input('date', '');

        $query = Order::with(['user', 'service', 'payments', 'checkout.payments', 'orderProcesses'])
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('order_code', 'like', "%{$search}%")
                   ->orWhere('customer_name', 'like', "%{$search}%")
                   ->orWhere('customer_phone', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                   ->orWhereHas('service', fn($s) => $s->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn($q) => $q->where('status', $status))
            // Belum lunas = tidak punya pembayaran paid (termasuk gagal/kadaluarsa), juga lewat checkout
            ->paymentStatus($paymentStatus)
            ->when($date, fn($q) => $q->whereDate('created_at', $date))
            ->latest();

        $orders = $query->paginate(10)->withQueryString();

        $stats = [
            'total'    => Order::count(),
            'pending'  => Order::where('status', 'pending')->count(),
            'diproses' => Order::where('status', 'diproses')->count(),
            'siap_diambil' => Order::where('status', Order::STATUS_SIAP_DIAMBIL)->count(),
            'selesai'  => Order::where('status', Order::STATUS_SELESAI)->count(),
        ];

        // Services aktif untuk form pemesanan
        $services = Service::aktif()->get();
        $customers = User::where('role', 'pelanggan')->select('id', 'name', 'phone')->get();

        return view('admin.orders.index', compact('orders', 'stats', 'services', 'customers', 'search', 'status', 'paymentStatus', 'date'));
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $this->orderService->create($request->validated());
            return back()->with('success', 'Pesanan berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status'      => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'machine_qty' => 'nullable|integer|min:1|max:20',
        ]);

        try {
            if (isset($validated['machine_qty']) && $validated['machine_qty'] != $order->machine_qty) {
                $this->orderService->updateMachineQty($order, (int) $validated['machine_qty'], auth()->user());
            }

            if (isset($validated['status']) && $validated['status'] != $order->status) {
                $this->orderService->updateStatus($order, $validated['status'], auth()->user());
            }

            return back()->with('success', 'Pesanan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->delete();
        return back()->with('success', 'Pesanan berhasil dihapus.');
    }

    public function confirmPayment(Request $request, Order $order)
    {
        $validated = $request->validate([
            'method' => 'required|in:cash,transfer,e-wallet',
        ]);

        if ($order->isPaid()) {
            return back()->with('error', 'Pesanan ini sudah lunas sebelumnya.');
        }

        app(\App\Services\PaymentService::class)->confirmManually($order, $validated['method'], $request->user());

        return back()->with('success', 'Pembayaran pesanan berhasil dikonfirmasi lunas.');
    }

    public function updateProcess(Request $request, \App\Models\OrderProcess $process)
    {
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
}
