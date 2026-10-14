<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Semua pengguna terotentikasi dapat melihat daftar layanan.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Semua pengguna terotentikasi dapat melihat detail layanan.
     */
    public function view(User $user, Service $service): bool
    {
        return true;
    }

    /**
     * Hanya Admin yang boleh membuat layanan baru.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya Admin yang boleh mengubah layanan.
     */
    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hanya Admin yang boleh menghapus layanan.
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }
}
