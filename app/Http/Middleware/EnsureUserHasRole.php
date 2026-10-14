<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * Format pemanggilan: ->middleware('role:admin') atau ->middleware('role:admin,pegawai')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()->role instanceof UserRole
            ? $request->user()->role->value
            : strtolower($request->user()->role ?? '');

        $allowedRoles = array_map('strtolower', $roles);

        if (! in_array($userRole, $allowedRoles)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
