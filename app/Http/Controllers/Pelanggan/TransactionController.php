<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPaidTransactionRequest;
use App\Models\Checkout;
use App\Models\Refund;
use App\Services\OnlineCancellationService;
use App\Support\TransactionTimeline;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;

/**
 * Nota satu transaksi pelanggan (ditunjukkan ke petugas outlet) + timeline dari order_status_logs,
 * dan pembatalan setelah bayar selama syaratnya terpenuhi.
 */
class TransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected OnlineCancellationService $cancellations) {}

    public function show(Checkout $checkout)
    {
        // Milik orang lain → 404 (CheckoutPolicy)
        $this->authorize('view', $checkout);

        // Belum dibayar tidak punya nota: lanjutkan atau batalkan di halaman pembayaran
        if ($checkout->isAwaitingPayment() && ! $checkout->isOutlet()) {
            return redirect()->route('pelanggan.pembayaran.show', $checkout);
        }

        $checkout->load(['user', 'orders.service', 'orders.statusLogs', 'payments', 'statusLogs', 'refund']);

        return view('pelanggan.transaksi.show', [
            'checkout'     => $checkout,
            'payment'      => $checkout->paymentForDisplay(),
            'timeline'     => TransactionTimeline::for($checkout),
            'cancellation' => $this->cancellations->eligibility($checkout),
        ]);
    }

    public function cancel(CancelPaidTransactionRequest $request, Checkout $checkout)
    {
        $this->authorize('view', $checkout);

        try {
            $refund = $this->cancellations->cancel($checkout, $request->user(), $request->bankAccount(), $request->validated('reason'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('pelanggan.transaksi.show', $checkout)->with('success', match ($refund->status) {
            Refund::STATUS_DONE   => 'Pesanan dibatalkan dan dana sudah dikembalikan ke metode pembayaran Anda.',
            Refund::STATUS_FAILED => 'Pesanan dibatalkan. Refund otomatis belum berhasil, admin akan memprosesnya.',
            default               => $refund->isApi()
                ? 'Pesanan dibatalkan. Refund sedang diproses Midtrans.'
                : 'Pesanan dibatalkan. Admin akan mentransfer refund ke rekening Anda.',
        });
    }
}
