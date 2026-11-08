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
}
