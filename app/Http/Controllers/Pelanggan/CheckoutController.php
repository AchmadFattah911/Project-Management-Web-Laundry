<?php

namespace App\Http\Controllers\Pelanggan;

use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Checkout;
use App\Models\Order;
use App\Services\CheckoutResult;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Keranjang → transaksi (checkout), lalu halaman & aksi pembayaran transaksi tersebut.
 */
class CheckoutController extends Controller
{
    use AuthorizesRequests;

    public const RECENT_ORDERS_LIMIT = 5;

    public function __construct(
        protected CheckoutService $checkoutService,
        protected PaymentService $paymentService,
    ) {}

    public function store(StoreCheckoutRequest $request)
    {
        $result = $this->checkoutService->createFromCart($request->user(), $request->validated());
        $code = $result->checkout->code;

        // Submit ganda tidak pernah jadi halaman error: selalu ke transaksi yang sudah ada + penjelasan
        [$type, $message] = match ($result->outcome) {
            CheckoutResult::CREATED  => ['success', "Pesanan {$code} berhasil dibuat. Silakan selesaikan pembayaran."],
            CheckoutResult::REPLAYED => ['status', "Pesanan {$code} sudah dibuat sebelumnya. Ini transaksi yang sama, tidak ada pesanan ganda."],
            CheckoutResult::MISMATCH => ['error', "Form ini sudah dipakai untuk pesanan {$code}, jadi perubahan terakhir tidak disimpan. Untuk pesanan dengan isi berbeda, buat pesanan baru dari keranjang."],
        };

        return redirect()->route('pelanggan.pembayaran.show', $result->checkout)->with($type, $message);
    }

    public function show(Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        // Sudah lunas: tidak ada lagi yang perlu dibayar, langsung ke halaman setelah pembayaran
        if ($checkout->isPaid()) {
            return redirect()->route('pelanggan.pembayaran.selesai', $checkout);
        }

        $checkout->load(['orders.service', 'payments']);

        return view('pelanggan.pembayaran.show', [
            'checkout'       => $checkout,
            'payment'        => $checkout->paymentForDisplay(),
            'paymentMethods' => config('laundry.online_payment_methods', []),
        ]);
    }

    /**
     * Halaman setelah pembayaran: konfirmasi lunas + riwayat pemesanan.
     */
    public function finished(Request $request, Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        if (! $checkout->isPaid()) {
            return redirect()->route('pelanggan.pembayaran.show', $checkout)
                ->with('status', 'Transaksi ini belum dibayar. Silakan selesaikan pembayaran terlebih dahulu.');
        }

        $checkout->load(['orders.service', 'orders.orderProcesses', 'payments']);

        // Riwayat pemesanan lain (di luar transaksi ini) sebagai pratinjau; daftar lengkap ada di halaman Akun
        $recentOrders = Order::with(['service', 'payments', 'checkout.payments'])
            ->where('user_id', $request->user()->id)
            ->where(fn ($q) => $q->whereNull('checkout_id')->orWhere('checkout_id', '!=', $checkout->id))
            ->latest()
            ->take(self::RECENT_ORDERS_LIMIT)
            ->get();

        return view('pelanggan.pembayaran.selesai', [
            'checkout'       => $checkout,
            'payment'        => $checkout->paymentForDisplay(),
            'recentOrders'   => $recentOrders,
            'paymentMethods' => config('laundry.online_payment_methods', []),
        ]);
    }

    /**
     * Status ringan untuk dipantau halaman pembayaran (polling) setelah pelanggan kembali dari gateway,
     * karena notifikasi gateway asli bisa datang beberapa detik setelah pelanggan kembali.
     */
    public function status(Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        $payment = $checkout->paymentForDisplay();

        return response()->json([
            'paid'           => $checkout->isPaid(),
            'status'         => $checkout->status,
            'payment_status' => $payment?->displayStatus(),
        ]);
    }

    /**
     * POST /pembayaran/{code}/bayar
     *
     * Token pembayaran dihitung server dari transaksi ini, bukan dikirim browser (lihat PaymentService::paymentToken).
     * Token yang sudah pernah diproses tidak diproses lagi: hasil sebelumnya dikembalikan (tagihan yang sama).
     */
    public function pay(Request $request, Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentService::onlineMethods())],
        ], [
            'payment_method.required' => 'Silakan pilih metode pembayaran.',
            'payment_method.in'       => 'Metode pembayaran tidak valid.',
        ]);

        try {
            $result = $this->paymentService->pay($checkout, $validated['payment_method'], $request->user());
        } catch (IdempotencyException $e) {
            return $this->failed($request, $checkout, $e->getMessage(), $e->httpStatus());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->failed($request, $checkout, $e->getMessage(), 422);
        }

        $response = $result['response'];

        if ($request->expectsJson()) {
            return response()
                ->json(['data' => $response, 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201)
                ->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
        }

        return redirect()->away($response['checkout_url']);
    }

    protected function failed(Request $request, Checkout $checkout, string $message, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->route('pelanggan.pembayaran.show', $checkout)->with('error', $message);
    }
}
