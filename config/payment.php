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
    'default' => env('PAYMENT_ENABLED', false) ? 'midtrans' : env('PAYMENT_GATEWAY', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | Flag Pembayaran Midtrans
    |--------------------------------------------------------------------------
    | false (default) = website berperilaku seperti sebelumnya (gateway di PAYMENT_GATEWAY, mis. dummy).
    | true            = transaksi keranjang dibayar lewat Midtrans Snap.
    */
    'enabled' => (bool) env('PAYMENT_ENABLED', false),

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
        'midtrans' => [
            'class'          => App\Payments\Gateways\MidtransGateway::class,
            // SERVER_KEY hanya di backend; CLIENT_KEY boleh tampil di halaman (snap.js)
            'server_key'     => env('MIDTRANS_SERVER_KEY'),
            'client_key'     => env('MIDTRANS_CLIENT_KEY'),
            'is_production'  => (bool) env('MIDTRANS_IS_PRODUCTION', false),
            'timeout'        => (int) env('MIDTRANS_HTTP_TIMEOUT', 15),
            // Tagihan yang sedang dibuat (belum ada snap_token) lebih lama dari ini dianggap macet → ditutup, dibuat -P{n+1}
            'in_flight_seconds' => (int) env('MIDTRANS_IN_FLIGHT_SECONDS', 45),
            // Kartu kredit sengaja tidak diaktifkan (payments.method hanya transfer / e-wallet)
            'enabled_payments' => array_values(array_filter(array_map('trim', explode(',', (string) env(
                'MIDTRANS_ENABLED_PAYMENTS',
                'bca_va,bni_va,bri_va,permata_va,echannel,other_va,gopay,shopeepay,qris'
            ))))),
        ],

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
