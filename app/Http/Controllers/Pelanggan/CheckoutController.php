<?php

namespace App\Http\Controllers\Pelanggan;

use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Checkout;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Keranjang → transaksi (checkout), lalu halaman & aksi pembayaran transaksi tersebut.
 */
class CheckoutController extends Controller
{
    use AuthorizesRequests;

    public const IDEMPOTENCY_SCOPE = 'checkout-payment';

    public const RECENT_ORDERS_LIMIT = 5;

    public function __construct(
        protected CheckoutService $checkoutService,
        protected PaymentService $paymentService,
        protected IdempotencyService $idempotency,
    ) {}

    public function store(StoreCheckoutRequest $request)
    {
        $checkout = $this->checkoutService->createFromCart($request->user(), $request->validated());

        return redirect()->route('pelanggan.pembayaran.show', $checkout)
            ->with('success', "Pesanan {$checkout->code} berhasil dibuat. Silakan selesaikan pembayaran.");
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
            // Key baru setiap halaman dimuat. Klik ganda / kirim ulang form yang sama = key sama = diproses 1x.
            'idempotencyKey' => (string) Str::uuid(),
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
                ->with('status', 'Transaksi ini belum lunas. Silakan selesaikan pembayaran terlebih dahulu.');
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
     * Wajib membawa idempotency key (header "Idempotency-Key" atau field "idempotency_key").
     * Key yang sudah pernah diproses tidak diproses lagi: hasil sebelumnya dikembalikan (tagihan yang sama).
     */
    public function pay(Request $request, Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'payment_method'  => ['required', Rule::in(PaymentService::onlineMethods())],
        ], [
            'idempotency_key.*'       => 'Kode permintaan pembayaran tidak valid. Muat ulang halaman lalu coba lagi.',
            'payment_method.required' => 'Silakan pilih metode pembayaran.',
            'payment_method.in'       => 'Metode pembayaran tidak valid.',
        ]);

        $key = $validated['idempotency_key'];

        try {
            $result = $this->idempotency->run(
                key: $key,
                scope: self::IDEMPOTENCY_SCOPE,
                user: $request->user(),
                payload: ['checkout_id' => $checkout->id, 'payment_method' => $validated['payment_method']],
                callback: function () use ($checkout, $validated, $key) {
                    $payment = $this->paymentService->startCheckoutPayment($checkout, $validated['payment_method'], $key);

                    return [
                        'payment_id'   => $payment->id,
                        'reference'    => $payment->reference,
                        'checkout_url' => $payment->checkout_url,
                        'status'       => $payment->status,
                    ];
                },
            );
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
