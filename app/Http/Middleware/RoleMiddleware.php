<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Gunakan: ->middleware('role:admin') atau ->middleware('role:admin,operator')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $userRole = strtolower($request->user()->role);

        if (! in_array($userRole, array_map('strtolower', $roles))) {
            // Redirect ke halaman yang sesuai dengan role mereka
            return match ($userRole) {
                'admin'    => redirect()->route('admin.dashboard'),
                'operator' => redirect()->route('operator.dashboard'),
                'kurir'    => redirect()->route('kurir.dashboard'),
                default    => redirect()->route('home'),   // pelanggan
            };
        }

        return $next($request);
    }
}
