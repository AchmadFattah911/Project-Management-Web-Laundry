<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Default
    |--------------------------------------------------------------------------
    | Gateway yang dipakai untuk pembayaran online pelanggan.
    | Saat ini hanya 'dummy' (untuk testing). Untuk gateway asli nanti:
    | 1. Buat class baru yang meng-implement App\Contracts\PaymentGateway
    | 2. Daftarkan di 'gateways' di bawah
    | 3. Ganti PAYMENT_GATEWAY di .env
    */
    'default' => env('PAYMENT_GATEWAY', 'dummy'),

    // Batas waktu pembayaran sebelum tagihan dianggap kadaluarsa (menit)
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Idempotency Pembayaran
    |--------------------------------------------------------------------------
    | Setiap request bayar membawa 1 key unik. Key yang sedang diproses dikunci selama
    | lock_seconds; jika proses mati di tengah jalan, key boleh dipakai ulang setelah lewat.
    | Key lama dihapus otomatis (php artisan model:prune) setelah retention_days.
    */
    'idempotency' => [
        'lock_seconds'   => (int) env('PAYMENT_IDEMPOTENCY_LOCK_SECONDS', 60),
        'retention_days' => (int) env('PAYMENT_IDEMPOTENCY_RETENTION_DAYS', 30),
    ],

    'gateways' => [
        'dummy' => [
            'class'   => App\Payments\Gateways\DummyGateway::class,
            // Secret untuk tanda tangan (HMAC) notifikasi webhook dummy
            'secret'  => env('PAYMENT_DUMMY_SECRET', 'dummy-payment-secret'),
            // Halaman simulasi pembayaran untuk tester. Selalu nonaktif di production.
            'enabled' => (bool) env('PAYMENT_DUMMY_ENABLED', true),
            // Simulasi gangguan gateway (0-100 %): createCharge gagal acak, untuk menguji retry + idempotency
            'fail_rate' => (int) env('PAYMENT_DUMMY_FAIL_RATE', 0),
        ],
    ],
];
