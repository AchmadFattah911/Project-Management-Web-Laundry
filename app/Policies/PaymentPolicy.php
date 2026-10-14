<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Pelanggan hanya boleh melihat pembayaran untuk pesanan miliknya sendiri.
     * Admin dan Pegawai dapat melihat semua pembayaran.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin() || $user->isPegawai()) {
            return true;
        }

        return $payment->ownerId() === (int) $user->id;
    }

    /**
     * Hanya Admin dan Pegawai yang berwenang membuat atau mengatur status menjadi 'paid'.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isPegawai();
    }

    /**
     * Hanya Admin dan Pegawai yang berwenang mengubah status pembayaran menjadi 'paid'.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $user->isPegawai();
    }

    /**
     * Hanya Admin yang boleh menghapus data pembayaran.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
