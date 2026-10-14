<?php

namespace App\Policies;

use App\Models\Checkout;
use App\Models\User;

class CheckoutPolicy
{
    /**
     * Transaksi hanya bisa dilihat & dibayar oleh pelanggan pemiliknya.
     */
    public function view(User $user, Checkout $checkout): bool
    {
        return (int) $checkout->user_id === (int) $user->id;
    }
}
