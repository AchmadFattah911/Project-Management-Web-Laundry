<?php

namespace App\Policies;

use App\Models\CashShift;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CashShiftPolicy
{
    /**
     * Pegawai hanya melihat shift miliknya; admin (shift.view_all) melihat semua. Selain itu 404.
     */
    public function view(User $user, CashShift $shift): Response
    {
        return (int) $shift->user_id === (int) $user->id || $user->hasPermission('shift.view_all')
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
