<?php

namespace App\Policies;

use App\Models\Checkout;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CheckoutPolicy
{
    /**
     * Transaksi hanya bisa dilihat & dibayar oleh pelanggan pemiliknya.
     * Milik orang lain dijawab 404 (bukan 403) agar keberadaan kode transaksi tidak terungkap.
     */
    public function view(User $user, Checkout $checkout): Response
    {
        return (int) $checkout->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Halaman transaksi panel kasir: outlet → transaksi.outlet.view; online lunas → transaksi.online.view;
     * online yang belum/tidak lunas hanya untuk audit pembayaran (admin). Selain itu 404.
     */
    public function viewInPanel(User $user, Checkout $checkout): Response
    {
        $permission = match (true) {
            $checkout->isOutlet() => 'transaksi.outlet.view',
            $checkout->isPaid()   => 'transaksi.online.view',
            default               => 'transaksi.audit_pembayaran.view',
        };

        return $user->hasPermission($permission) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Halaman pembayaran online: hanya transaksi online milik pelanggan. Transaksi outlet dibayar di kasir → 404.
     */
    public function pay(User $user, Checkout $checkout): Response
    {
        return (int) $checkout->user_id === (int) $user->id && ! $checkout->isOutlet()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
