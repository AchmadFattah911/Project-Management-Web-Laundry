<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Payments\Gateways\DummyGateway;
use App\Payments\InvalidPaymentNotification;
use App\Services\PaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Halaman checkout palsu untuk tester (pengganti halaman pembayaran gateway asli).
 * Hanya aktif jika PAYMENT_DUMMY_ENABLED=true dan bukan environment production.
 */
class DummyCheckoutController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function show(string $reference)
    {
        $payment = $this->findPayment($reference);

        return view('payments.dummy-checkout', [
            'payment' => $payment->load(['order.service', 'checkout.orders.service']),
            'methods' => config('laundry.online_payment_methods', []),
        ]);
    }

    /**
     * Mensimulasikan hasil pembayaran. Notifikasi dikirim lewat jalur yang sama dengan webhook
     * gateway asli (lengkap dengan signature), sehingga logika yang diuji identik.
     */
    public function simulate(Request $request, string $reference, DummyGateway $gateway)
    {
        $payment = $this->findPayment($reference);

        $validated = $request->validate([
            'result'    => 'required|in:paid,failed,expired',
            // Kirim notifikasi yang sama 2x (seperti gateway yang retry webhook) untuk membuktikan idempotency
            'duplicate' => 'nullable|boolean',
        ]);

        $notification = $gateway->buildNotification($payment, $validated['result']);
        $sends = ! empty($validated['duplicate']) ? 2 : 1;

        try {
            for ($i = 0; $i < $sends; $i++) {
                $payment = $this->paymentService->handleNotification($gateway->name(), $notification['body'], $notification['headers']);
            }
        } catch (InvalidPaymentNotification $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = match ($payment->status) {
            Payment::STATUS_PAID    => 'Pembayaran berhasil. Pesanan Anda sudah lunas.',
            Payment::STATUS_FAILED  => 'Pembayaran gagal. Silakan buat tagihan baru untuk mencoba lagi.',
            Payment::STATUS_EXPIRED => 'Tagihan pembayaran kadaluarsa. Silakan buat tagihan baru.',
            default                 => 'Status pembayaran belum berubah.',
        };

        if ($sends > 1) {
            $message .= " (Simulasi: notifikasi dikirim {$sends}x, diproses " . $payment->events()->count() . 'x.)';
        }

        // Transaksi keranjang: lunas → halaman selesai, selain itu kembali ke halaman pembayaran.
        // Order tunggal lama → detail order.
        $redirect = $payment->checkout_id
            ? redirect()->route($payment->isPaid() ? 'pelanggan.pembayaran.selesai' : 'pelanggan.pembayaran.show', $payment->checkout->code)
            : redirect()->route('pelanggan.orders.show', $payment->order_id);

        return $redirect->with($payment->isPaid() ? 'success' : 'error', $message);
    }

    protected function findPayment(string $reference): Payment
    {
        abort_unless(PaymentService::dummyEnabled(), 404);

        $payment = Payment::where('gateway', 'dummy')->where('reference', $reference)->firstOrFail();

        $this->authorize('view', $payment);

        return $payment;
    }
}
