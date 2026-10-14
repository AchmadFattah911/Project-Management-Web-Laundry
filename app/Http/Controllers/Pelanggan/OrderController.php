<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Services\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    public function index(Request $request)
    {
        // Filter tab yang sama dengan halaman Akun (semua / diproses / selesai)
        $tab = array_key_exists($request->query('status'), Order::STATUS_GROUPS)
            ? $request->query('status')
            : 'semua';

        $orders = Order::with(['service', 'payments', 'checkout.payments', 'orderProcesses'])
            ->where('user_id', auth()->id())
            ->statusGroup($tab)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pelanggan.orders.index', compact('orders', 'tab'));
    }

    /**
     * Pintu masuk tombol "Pesan Sekarang" di landing page.
     * Tamu akan diarahkan login dulu (middleware auth menyimpan URL ini sebagai intended),
     * lalu masuk ke halaman pemesanan dengan layanan ini langsung tercentang.
     */
    public function start(Service $service)
    {
        if ($service->isSelfService()) {
            return redirect()->to(route('home') . '#layanan')
                ->with('error', "Layanan '{$service->name}' adalah Self Laundry dan hanya bisa digunakan langsung di outlet.");
        }

        if ($service->status !== 'tersedia') {
            return redirect()->to(route('home') . '#layanan')
                ->with('error', "Layanan '{$service->name}' sedang tidak tersedia untuk dipesan.");
        }

        return redirect()->route('pelanggan.pesan.index', ['layanan' => $service->slug]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['service', 'payments', 'checkout.payments', 'orderProcesses.pegawai']);

        return view('pelanggan.orders.show', [
            'order'          => $order,
            'payment'        => $order->paymentForDisplay(),
            'isPaid'         => $order->isPaid(),
            'paymentMethods' => config('laundry.online_payment_methods', []),
        ]);
    }

    /**
     * Membuat tagihan baru untuk order tunggal lama (sebelum ada keranjang), pertama kali
     * atau setelah tagihan sebelumnya gagal/kadaluarsa. Order dari keranjang dibayar per transaksi.
     */
    public function pay(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentService::onlineMethods())],
        ], [
            'payment_method.required' => 'Silakan pilih metode pembayaran.',
            'payment_method.in'       => 'Metode pembayaran tidak valid.',
        ]);

        try {
            $payment = $this->paymentService->startOnlinePayment($order, $validated['payment_method']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($payment->checkout_url);
    }
}
