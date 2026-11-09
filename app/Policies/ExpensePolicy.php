<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpensePolicy
{
    /**
     * Nota pengeluaran: pembuatnya atau yang berhak melihat semua (admin). Selain itu 404.
     */
    public function view(User $user, Expense $expense): Response
    {
        return (int) $expense->created_by === (int) $user->id || $user->hasPermission('pengeluaran.view_all')
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
