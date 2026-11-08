<?php

namespace App\Http\Controllers\Pegawai;

use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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

        // Hanya pesanan yang sudah dibayar: yang belum dibayar belum menjadi pesanan
        $query = Order::with(['user', 'service', 'payments', 'checkout.payments', 'orderProcesses'])
            ->paymentStatus('paid')
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
            $result = $this->orderService->createWalkIn($request->validated(), $request->user());
        } catch (InvalidArgumentException|IdempotencyException $e) {
            return back()->with('error', $e->getMessage())->withInput($request->except('submission_token'));
        }

        return back()->with('success', $this->walkInMessage($result));
    }

    /**
     * @param  array{response: array<string, mixed>, replayed: bool}  $result
     */
    protected function walkInMessage(array $result): string
    {
        $code = $result['response']['order_code'];

        if ($result['replayed']) {
            return "Pesanan {$code} sudah tersimpan, tidak dibuat ulang.";
        }

        $change = (float) ($result['response']['change'] ?? 0);

        return "Pesanan walk-in berhasil dibuat dengan kode {$code}."
            . ($change > 0 ? ' Kembalian Rp ' . number_format($change, 0, ',', '.') . '.' : '');
    }

    /**
     * Pegawai hanya mengubah status pesanan. Jumlah mesin, nominal, dan status pembayaran
     * terkunci karena pesanan yang tampil di sini sudah dibayar lunas.
     */
    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        try {
            $this->orderService->updateStatus($order, $validated['status'], auth()->user());

            return back()->with('success', 'Status pesanan berhasil diperbarui.');
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
}
