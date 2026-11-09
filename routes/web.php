<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DummyCheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\Panel\CustomerController as PanelCustomerController;
use App\Http\Controllers\Panel\ShiftController as PanelShiftController;
use App\Http\Controllers\Panel\TransactionController as PanelTransactionController;
use App\Http\Controllers\Panel\VoidController as PanelVoidController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Pegawai\DashboardController as PegawaiDashboardController;
use App\Http\Controllers\Pegawai\FinanceController as PegawaiFinanceController;
use App\Http\Controllers\Pegawai\OrderController as PegawaiOrderController;
use App\Http\Controllers\Pelanggan\AccountController as PelangganAccountController;
use App\Http\Controllers\Pelanggan\CartController as PelangganCartController;
use App\Http\Controllers\Pelanggan\CheckoutController as PelangganCheckoutController;
use App\Http\Controllers\Pelanggan\OrderController as PelangganOrderController;
use App\Http\Controllers\Pelanggan\TransactionController as PelangganTransactionController;
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
    ->middleware('throttle:60,1,tracking:')
    ->name('tracking');

// Notifikasi status pembayaran dari payment gateway (tanpa CSRF, diverifikasi lewat signature)
Route::post('/payments/webhook/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:120,1,payments.webhook:')
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
        Route::post('/orders', [AdminOrderController::class, 'store'])->middleware('throttle:30,1,orders.store:')->name('orders.store');
        Route::put('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
        Route::put('/orders/process/{process}', [AdminOrderController::class, 'updateProcess'])->name('orders.process');
        Route::post('/orders/{order}/payment', [AdminOrderController::class, 'confirmPayment'])->name('orders.payment');
        // Transaksi online: cek status ke Midtrans (jalur sama dengan webhook), pengganti lunas manual
        Route::post('/orders/{order}/cek-midtrans', [AdminOrderController::class, 'checkMidtrans'])->middleware('throttle:20,1,admin.check-midtrans:')->name('orders.check-midtrans');

        // Pembayaran yang perlu direfund (nominal beda, bayar ganda, bayar setelah transaksi ditutup)
        Route::get('/perlu-refund', [AdminRefundController::class, 'index'])->name('refunds.index');
        Route::post('/perlu-refund/{payment}', [AdminRefundController::class, 'markRefunded'])->name('refunds.mark');

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
        Route::post('/orders', [PegawaiOrderController::class, 'store'])->middleware('throttle:30,1,orders.store:')->name('orders.store');
        Route::put('/orders/{order}', [PegawaiOrderController::class, 'update'])->name('orders.update');

        // Update tahapan pengerjaan (cuci, kering, lipat)
        Route::put('/orders/process/{process}', [PegawaiOrderController::class, 'updateProcess'])->name('orders.process');

        // Audit & rekap keuangan (hanya baca)
        // Laporan keuangan kini hanya admin (panel.laporan.*); URL lama diarahkan ke ringkasan shift milik pegawai
        Route::get('/keuangan', [PegawaiFinanceController::class, 'index'])->name('keuangan.index');
        Route::get('/keuangan/export', [PegawaiFinanceController::class, 'export'])->name('keuangan.export');
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
        // Detail transaksi keranjang + timeline (milik orang lain → 404)
        Route::get('/transaksi/{checkout:code}', [PelangganTransactionController::class, 'show'])->name('transaksi.show');
        Route::post('/orders/{order}/pay', [PelangganOrderController::class, 'pay'])->name('orders.pay');
    });

// Halaman pemesanan + keranjang (terpisah dari landing page)
Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('pesan')
    ->name('pelanggan.')
    ->group(function () {
        Route::get('/', [PelangganCartController::class, 'index'])->name('pesan.index');
        Route::get('/keranjang', [PelangganCartController::class, 'cart'])->name('cart.index');
        Route::post('/keranjang', [PelangganCartController::class, 'store'])->middleware('throttle:30,1,cart.store:')->name('cart.store');
        Route::patch('/keranjang/{item}', [PelangganCartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('/keranjang/{item}', [PelangganCartController::class, 'destroy'])->whereNumber('item')->name('cart.destroy');
        Route::post('/checkout', [PelangganCheckoutController::class, 'store'])->middleware('throttle:10,1,checkout.store:')->name('checkout.store');
    });

// Halaman pembayaran transaksi (1 transaksi = beberapa order dari keranjang)
Route::middleware(['auth', 'verified', 'role:pelanggan'])
    ->prefix('pembayaran')
    ->name('pelanggan.pembayaran.')
    ->group(function () {
        Route::get('/{checkout:code}', [PelangganCheckoutController::class, 'show'])->name('show');
        Route::get('/{checkout:code}/selesai', [PelangganCheckoutController::class, 'finished'])->name('selesai');
        Route::get('/{checkout:code}/status', [PelangganCheckoutController::class, 'status'])->middleware('throttle:60,1,status:')->name('status');
        Route::post('/{checkout:code}/bayar', [PelangganCheckoutController::class, 'pay'])->middleware('throttle:20,1,pay:')->name('pay');
        // Milik orang lain → 404 (policy); rate limit per pelanggan
        Route::post('/{checkout:code}/batal', [PelangganCheckoutController::class, 'cancel'])->middleware('throttle:10,1,cancel:')->name('cancel');
        Route::post('/{checkout:code}/popup-ditutup', [PelangganCheckoutController::class, 'popupClosed'])->middleware('throttle:30,1,popup-closed:')->name('popup-closed');
        Route::post('/{checkout:code}/pesan-lagi', [PelangganCheckoutController::class, 'reorder'])->middleware('throttle:10,1,reorder:')->name('reorder');
    });

// Halaman Akun pelanggan (profil + riwayat pemesanan, gaya aplikasi mobile)
Route::get('/akun', [PelangganAccountController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:pelanggan'])
    ->name('pelanggan.akun');

// Dashboard pelanggan lama sudah digantikan halaman Akun; URL lama tetap diarahkan
Route::redirect('/pelanggan/dashboard', '/akun');

// ─────────────────────────────────────────────
//  Panel Kasir & Keuangan (admin + pegawai, dibatasi permission per aksi: config/permissions.php)
// ─────────────────────────────────────────────

// {customer} hanya akun pelanggan; id akun staf → 404
Route::bind('customer', fn (string $value) => \App\Models\User::where('role', \App\Enums\UserRole::PELANGGAN->value)->findOrFail($value));

Route::middleware(['auth', 'verified', 'role:admin,pegawai'])
    ->prefix('panel')
    ->name('panel.')
    ->group(function () {
        // Data pelanggan
        Route::get('/pelanggan', [PanelCustomerController::class, 'index'])->middleware('can:pelanggan.view')->name('pelanggan.index');
        Route::get('/pelanggan/cari', [PanelCustomerController::class, 'search'])->middleware(['can:pelanggan.view', 'throttle:60,1,panel.pelanggan.cari:'])->name('pelanggan.search');
        Route::post('/pelanggan', [PanelCustomerController::class, 'store'])->middleware(['can:pelanggan.create', 'throttle:30,1,panel.pelanggan.store:'])->name('pelanggan.store');
        Route::post('/pelanggan/gabung', [PanelCustomerController::class, 'merge'])->middleware(['can:pelanggan.merge', 'throttle:10,1,panel.pelanggan.merge:'])->name('pelanggan.merge');
        Route::get('/pelanggan/{customer}', [PanelCustomerController::class, 'show'])->middleware('can:pelanggan.view')->name('pelanggan.show');
        Route::put('/pelanggan/{customer}', [PanelCustomerController::class, 'update'])->middleware(['can:pelanggan.update', 'throttle:30,1,panel.pelanggan.update:'])->name('pelanggan.update');
        Route::post('/pelanggan/{customer}/nonaktif', [PanelCustomerController::class, 'deactivate'])->middleware(['can:pelanggan.deactivate', 'throttle:20,1,panel.pelanggan.nonaktif:'])->name('pelanggan.deactivate');
        Route::post('/pelanggan/{customer}/aktifkan', [PanelCustomerController::class, 'activate'])->middleware(['can:pelanggan.deactivate', 'throttle:20,1,panel.pelanggan.aktifkan:'])->name('pelanggan.activate');

        // Transaksi: tab Outlet (CRU + void) dan tab Aplikasi (hanya lunas, baca + status pengerjaan)
        Route::get('/transaksi', [PanelTransactionController::class, 'index'])->name('transaksi.index');
        Route::get('/transaksi/baru', [PanelTransactionController::class, 'create'])->middleware('can:transaksi.outlet.create')->name('transaksi.create');
        Route::post('/transaksi', [PanelTransactionController::class, 'store'])->middleware(['can:transaksi.outlet.create', 'throttle:30,1,panel.transaksi.store:'])->name('transaksi.store');
        Route::get('/transaksi/{checkout}', [PanelTransactionController::class, 'show'])->name('transaksi.show');
        Route::get('/transaksi/{checkout}/struk', [PanelTransactionController::class, 'receipt'])->name('transaksi.receipt');
        Route::put('/transaksi/{checkout}', [PanelTransactionController::class, 'update'])->middleware(['can:transaksi.outlet.update', 'throttle:30,1,panel.transaksi.update:'])->name('transaksi.update');
        Route::post('/transaksi/{checkout}/lunasi', [PanelTransactionController::class, 'settle'])->middleware(['can:transaksi.outlet.settle', 'throttle:30,1,panel.transaksi.settle:'])->name('transaksi.settle');
        Route::post('/transaksi/{checkout}/void', [PanelVoidController::class, 'store'])->middleware(['can:transaksi.void.request', 'throttle:10,1,panel.transaksi.void:'])->name('transaksi.void');
        Route::put('/pesanan/{order}/status', [PanelTransactionController::class, 'updateOrderStatus'])->middleware(['can:transaksi.status.update', 'throttle:60,1,panel.pesanan.status:'])->name('transaksi.order-status');

        // Kas harian / shift
        Route::get('/shift', [PanelShiftController::class, 'index'])->middleware('can:shift.view_own')->name('shift.index');
        Route::post('/shift', [PanelShiftController::class, 'open'])->middleware(['can:shift.open', 'throttle:10,1,panel.shift.open:'])->name('shift.open');
        Route::get('/shift/{shift}', [PanelShiftController::class, 'show'])->middleware('can:shift.view_own')->name('shift.show');
        Route::post('/shift/{shift}/tutup', [PanelShiftController::class, 'close'])->middleware(['can:shift.close', 'throttle:10,1,panel.shift.close:'])->name('shift.close');
        Route::post('/shift/{shift}/verifikasi', [PanelShiftController::class, 'verify'])->middleware(['can:shift.verify', 'throttle:30,1,panel.shift.verify:'])->name('shift.verify');

        // Persetujuan void (admin)
        Route::get('/void', [PanelVoidController::class, 'index'])->middleware('can:transaksi.void.approve')->name('void.index');
        Route::post('/void/{voidRequest}/setujui', [PanelVoidController::class, 'approve'])->middleware(['can:transaksi.void.approve', 'throttle:30,1,panel.void.approve:'])->name('void.approve');
        Route::post('/void/{voidRequest}/tolak', [PanelVoidController::class, 'reject'])->middleware(['can:transaksi.void.approve', 'throttle:30,1,panel.void.reject:'])->name('void.reject');
    });

// ─────────────────────────────────────────────
//  Profile (Semua Pengguna Terotentikasi)
// ─────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
