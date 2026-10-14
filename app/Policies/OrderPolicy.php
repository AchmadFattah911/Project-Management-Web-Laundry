<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Admin dan Pegawai dapat melihat daftar semua pesanan.
     * Pelanggan dapat melihat daftar pesanannya sendiri.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Pelanggan hanya boleh melihat detail pesanan miliknya sendiri.
     * Admin dan Pegawai dapat melihat seluruh pesanan.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $user->isPegawai()) {
            return true;
        }

        return $order->user_id !== null && ((int) $order->user_id === (int) $user->id);
    }

    /**
     * Semua pengguna yang terotentikasi dapat membuat pesanan.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Hanya Admin dan Pegawai yang berwenang mengubah pesanan / status / jumlah mesin.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin() || $user->isPegawai();
    }

    /**
     * Admin boleh menghapus pesanan, KECUALI pesanan yang sudah memiliki pembayaran 'paid'.
     */
    public function delete(User $user, Order $order): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Admin TIDAK boleh menghapus order yang sudah lunas (termasuk yang dilunasi lewat checkout)
        if ($order->isPaid()) {
            return false;
        }

        // Order dari keranjang tidak dihapus satu per satu: total transaksi & tagihannya
        // sudah mencakup order ini, menghapusnya membuat nominal tagihan tidak cocok lagi.
        return $order->checkout_id === null;
    }
}
