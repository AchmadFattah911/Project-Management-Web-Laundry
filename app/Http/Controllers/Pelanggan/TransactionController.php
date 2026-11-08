<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Support\TransactionTimeline;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Detail satu transaksi pelanggan: item, pembayaran, dan timeline dari order_status_logs.
 */
class TransactionController extends Controller
{
    use AuthorizesRequests;

    public function show(Checkout $checkout)
    {
        // Milik orang lain → 404 (CheckoutPolicy)
        $this->authorize('view', $checkout);

        $checkout->load(['orders.service', 'orders.statusLogs', 'payments', 'statusLogs']);

        return view('pelanggan.transaksi.show', [
            'checkout' => $checkout,
            'payment'  => $checkout->paymentForDisplay(),
            'timeline' => TransactionTimeline::for($checkout),
        ]);
    }
}
