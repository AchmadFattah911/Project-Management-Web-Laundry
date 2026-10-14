<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    /**
     * Cari pesanan publik HANYA via order_code (LC-XXXXXX)
     * Mengembalikan data minimal tanpa data pribadi (tanpa nama/HP).
     */
    public function index(Request $request)
    {
        $orderCode = strtoupper(trim((string) $request->input('order_code', '')));
        $trackedOrder = null;
        $notFound = false;

        if (!empty($orderCode)) {
            $order = Order::with(['service'])
                ->where('order_code', $orderCode)
                ->first();

            if ($order) {
                $trackedOrder = [
                    'order_code'  => $order->order_code,
                    'service'     => $order->service?->name ?? 'Layanan Laundry',
                    'machine_qty' => $order->machine_qty,
                    'status'      => $order->status,
                    'created_at'  => $order->created_at->format('d M Y, H:i'),
                ];
            } else {
                $notFound = true;
            }
        }

        return view('tracking', compact('trackedOrder', 'orderCode', 'notFound'));
    }
}
