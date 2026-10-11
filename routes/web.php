<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\DeliveryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─────────────────────────────────────────────
//  Public Routes
// ─────────────────────────────────────────────

Route::get('/', function () {
    return Inertia::render('home', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');


// ─────────────────────────────────────────────
//  Dashboard Router (redirect sesuai role)
//  Dipakai sebagai fallback route('dashboard')
// ─────────────────────────────────────────────

Route::get('/dashboard', function () {
    $role = auth()->user()->role;

    return match ($role) {
        'admin'    => redirect()->route('admin.dashboard'),
        'operator' => redirect()->route('operator.dashboard'),
        'kurir'    => redirect()->route('kurir.dashboard'),
        default    => redirect()->route('home'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');


// ─────────────────────────────────────────────
//  Admin Dashboard
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/manajemen-users',          [UserController::class, 'index'])->name('users');
        Route::post('/manajemen-users',           [UserController::class, 'store'])->name('users.store');
        Route::put('/manajemen-users/{user}',     [UserController::class, 'update'])->name('users.update');
        Route::delete('/manajemen-users/{user}',  [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/manajemen-order',           [OrderController::class, 'index'])->name('orders');
        Route::post('/manajemen-order',            [OrderController::class, 'store'])->name('orders.store');
        Route::put('/manajemen-order/{order}',     [OrderController::class, 'update'])->name('orders.update');
        Route::delete('/manajemen-order/{order}',  [OrderController::class, 'destroy'])->name('orders.destroy');

        Route::get('/layanan-laundry',           [ServiceController::class, 'index'])->name('services');
        Route::post('/layanan-laundry',            [ServiceController::class, 'store'])->name('services.store');
        Route::put('/layanan-laundry/{service}',   [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/layanan-laundry/{service}',[ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('/pickup-delivery',              [DeliveryController::class, 'index'])->name('pickup');
        Route::post('/pickup-delivery',              [DeliveryController::class, 'store'])->name('pickup.store');
        Route::put('/pickup-delivery/{delivery}',   [DeliveryController::class, 'update'])->name('pickup.update');
        Route::delete('/pickup-delivery/{delivery}',[DeliveryController::class, 'destroy'])->name('pickup.destroy');
    });


// ─────────────────────────────────────────────
//  Operator Dashboard
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:operator'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('dashboard/operator/operator');
        })->name('dashboard');
    });


// ─────────────────────────────────────────────
//  Kurir Dashboard
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:kurir'])
    ->prefix('kurir')
    ->name('kurir.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('dashboard/kurir/kurir');
        })->name('dashboard');
    });


// ─────────────────────────────────────────────
//  Profile (semua role yang sudah login)
// ─────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
