<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Hanya Admin yang boleh melihat daftar seluruh pengguna.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin boleh melihat siapa saja, atau user boleh melihat dirinya sendiri.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Hanya Admin yang boleh membuat user secara manual.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin boleh mengupdate user apa saja, atau user boleh mengupdate profilnya sendiri.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Hanya Admin yang boleh menghapus user (dan tidak boleh menghapus dirinya sendiri).
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
