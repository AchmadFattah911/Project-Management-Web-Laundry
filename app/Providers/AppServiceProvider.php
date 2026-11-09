<?php

namespace App\Providers;

use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DummyGateway::class, fn () => new DummyGateway(
            secret: (string) config('payment.gateways.dummy.secret'),
            expiryMinutes: (int) config('payment.expiry_minutes', 60),
            failRate: (int) config('payment.gateways.dummy.fail_rate', 0),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Setiap permission di config/permissions.php menjadi Gate native (can:transaksi.void.approve, dst.)
        foreach (collect(config('permissions.roles', []))->flatten()->unique() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
