<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DummyCheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Pegawai\DashboardController as PegawaiDashboardController;
use App\Http\Controllers\Pegawai\OrderController as PegawaiOrderController;
use App\Http\Controllers\Pelanggan\AccountController as PelangganAccountController;
use App\Http\Controllers\Pelanggan\CartController as PelangganCartController;
use App\Http\Controllers\Pelanggan\CheckoutController as PelangganCheckoutController;
use App\Http\Controllers\Pelanggan\OrderController as PelangganOrderController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
//  Public Routes
// ─────────────────────────────────────────────

Route::get('/', [HomeController::class, 'index'])->name('home');

// Halaman informasi (menu Tentang Kami di halaman Akun)
Route::view('/tentang', 'pages.tentang')->name('tentang');
Route::view('/aturan-penggunaan', 'pages.aturan')->name('aturan');

// Tracking order publik (hanya via order_code, data minimal tanpa data pribadi, dengan rate limiting)
Route::get('/tracking', [OrderTrackingController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('tracking');

// Notifikasi status pembayaran dari payment gateway (tanpa CSRF, diverifikasi lewat signature)
Route::post('/payments/webhook/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

// Halaman checkout dummy untuk tester (otomatis 404 di production / jika dinonaktifkan)
Route::middleware('auth')->prefix('pembayaran/dummy')->name('payments.dummy.')->group(function () {
    Route::get('/{reference}', [DummyCheckoutController::class, 'show'])->name('show');
    Route::post('/{reference}', [DummyCheckoutController::class, 'simulate'])->name('simulate');
});


// ─────────────────────────────────────────────
//  Dashboard Router (Redirect Sesuai Role)
// ─────────────────────────────────────────────

Route::get('/dashboard', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }

    $role = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;

    return match ($role) {
        'admin'     => redirect()->route('admin.dashboard'),
        'pegawai'   => redirect()->route('pegawai.dashboard'),
        'pelanggan' => redirect()->route('home'),
        default     => redirect()->route('home'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

// ─────────────────────────────────────────────
//  Admin Panel (Role: admin)
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Manajemen User
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Manajemen Order
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [AdminOrderController::class, 'store'])->name('orders.store');
        Route::put('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
        Route::put('/orders/process/{process}', [AdminOrderController::class, 'updateProcess'])->name('orders.process');
        Route::post('/orders/{order}/payment', [AdminOrderController::class, 'confirmPayment'])->name('orders.payment');

        // Layanan Laundry
        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');
    });

// ─────────────────────────────────────────────
//  Pegawai Panel (Role: pegawai)
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:pegawai'])
    ->prefix('pegawai')
    ->name('pegawai.')
    ->group(function () {
        Route::get('/dashboard', [PegawaiDashboardController::class, 'index'])->name('dashboard');

        // Manajemen Order & Kasir Walk-in
        Route::get('/orders', [PegawaiOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [PegawaiOrderController::class, 'store'])->name('orders.store');
        Route::put('/orders/{order}', [PegawaiOrderController::class, 'update'])->name('orders.update');

        // Update tahapan pengerjaan (cuci, kering, lipat)
        Route::put('/orders/process/{process}', [PegawaiOrderController::class, 'updateProcess'])->name('orders.process');

        // Konfirmasi pelunasan pembayaran
        Route::post('/orders/{order}/payment', [PegawaiOrderController::class, 'confirmPayment'])->name('orders.payment');
    });

// ─────────────────────────────────────────────
//  Pelanggan Panel (Role: pelanggan)
// ─────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('pelanggan')
    ->name('pelanggan.')
    ->group(function () {
        // Riwayat & detail pesanan (pemesanan baru lewat /pesan)
        Route::get('/orders', [PelangganOrderController::class, 'index'])->name('orders.index');
        Route::get('/pesan/{service:slug}', [PelangganOrderController::class, 'start'])->name('orders.start');
        Route::get('/orders/{order}', [PelangganOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/pay', [PelangganOrderController::class, 'pay'])->name('orders.pay');
    });

// Halaman pemesanan + keranjang (terpisah dari landing page)
Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('pesan')
    ->name('pelanggan.')
    ->group(function () {
        Route::get('/', [PelangganCartController::class, 'index'])->name('pesan.index');
        Route::post('/keranjang', [PelangganCartController::class, 'store'])->middleware('throttle:30,1')->name('cart.store');
        Route::patch('/keranjang/{item}', [PelangganCartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('/keranjang/{item}', [PelangganCartController::class, 'destroy'])->whereNumber('item')->name('cart.destroy');
        Route::post('/checkout', [PelangganCheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    });

// Halaman pembayaran transaksi (1 transaksi = beberapa order dari keranjang)
Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('pembayaran')
    ->name('pelanggan.pembayaran.')
    ->group(function () {
        Route::get('/{checkout:code}', [PelangganCheckoutController::class, 'show'])->name('show');
        Route::get('/{checkout:code}/selesai', [PelangganCheckoutController::class, 'finished'])->name('selesai');
        Route::get('/{checkout:code}/status', [PelangganCheckoutController::class, 'status'])->middleware('throttle:60,1')->name('status');
        Route::post('/{checkout:code}/bayar', [PelangganCheckoutController::class, 'pay'])->middleware('throttle:20,1')->name('pay');
    });

// Halaman Akun pelanggan (profil + riwayat pemesanan, gaya aplikasi mobile)
Route::get('/akun', [PelangganAccountController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:pelanggan'])
    ->name('pelanggan.akun');

// Dashboard pelanggan lama sudah digantikan halaman Akun; URL lama tetap diarahkan
Route::redirect('/pelanggan/dashboard', '/akun');

// ─────────────────────────────────────────────
//  Profile (Semua Pengguna Terotentikasi)
// ─────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
