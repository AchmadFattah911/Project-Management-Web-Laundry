<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Halaman Akun pelanggan (pengganti dashboard pelanggan): profil + ringkasan riwayat pemesanan.
 */
class AccountController extends Controller
{
    /** Jumlah pesanan yang ditampilkan per tab; sisanya lewat "Lihat Semua". */
    public const PREVIEW_LIMIT = 5;

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = array_key_exists($request->query('status'), Order::STATUS_GROUPS)
            ? $request->query('status')
            : 'semua';

        $orders = Order::with(['service', 'payments', 'checkout.payments'])
            ->where('user_id', $user->id)
            ->statusGroup($tab)
            ->latest()
            ->take(self::PREVIEW_LIMIT)
            ->get();

        $counts = [
            'semua'    => Order::where('user_id', $user->id)->count(),
            'diproses' => Order::where('user_id', $user->id)->statusGroup('diproses')->count(),
            'selesai'  => Order::where('user_id', $user->id)->statusGroup('selesai')->count(),
        ];

        return view('pelanggan.akun', compact('user', 'orders', 'counts', 'tab'));
    }
}
