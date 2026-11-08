<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Services\CustomerOrderHistory;
use Illuminate\Http\Request;

/**
 * Halaman Akun pelanggan (pengganti dashboard pelanggan): profil + ringkasan riwayat pemesanan.
 */
class AccountController extends Controller
{
    /** Jumlah kartu transaksi yang ditampilkan per tab; sisanya lewat "Lihat Semua". */
    public const PREVIEW_LIMIT = 5;

    public function index(Request $request, CustomerOrderHistory $history)
    {
        $user = $request->user();
        $tab = CustomerOrderHistory::normalizeTab($request->query('status'));

        return view('pelanggan.akun', [
            'user'   => $user,
            'cards'  => $history->latest($user, $tab, self::PREVIEW_LIMIT),
            'counts' => $history->counts($user),
            'tab'    => $tab,
        ]);
    }
}
