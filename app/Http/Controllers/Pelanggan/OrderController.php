<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Services\CustomerOrderHistory;
use App\Services\PaymentService;
use App\Support\TransactionTimeline;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    public function index(Request $request, CustomerOrderHistory $history)
    {
        // Tab yang sama dengan halaman Akun; 1 kartu = 1 transaksi (walk-in lama = kartu sendiri)
        $tab = CustomerOrderHistory::normalizeTab($request->query('status'));

        return view('pelanggan.orders.index', [
            'cards'  => $history->paginate($request->user(), $tab, 10),
            'counts' => $history->counts($request->user()),
            'tab'    => $tab,
        ]);
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

        $order->load(['service', 'payments', 'checkout.payments', 'orderProcesses.pegawai', 'statusLogs']);

        return view('pelanggan.orders.show', [
            'order'          => $order,
            'timeline'       => TransactionTimeline::for($order),
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

        // Pesanan tunggal lama tidak dibayar lewat Midtrans (hanya transaksi keranjang)
        if (\App\Services\SnapPaymentService::enabled()) {
            return back()->with('error', 'Pembayaran online untuk pesanan lama ini tidak tersedia. Silakan bayar langsung di outlet.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentService::onlineMethods())],
        ], [
            'payment_method.required' => 'Silakan pilih metode pembayaran.',
            'payment_method.in'       => 'Metode pembayaran tidak valid.',
        ]);

        try {
            $result = $this->paymentService->pay($order, $validated['payment_method'], $request->user());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($result['response']['checkout_url']);
    }
}
