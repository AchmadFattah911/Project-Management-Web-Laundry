<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Informasi Toko / Outlet Laundry Coin
    |--------------------------------------------------------------------------
    | Konfigurasi ini digunakan pada halaman publik, landing page, footer, dan kontak.
    | Silakan sesuaikan nilai di file .env atau langsung edit nilai default di bawah ini.
    */

    'store_name' => env('LAUNDRY_STORE_NAME', 'Laundry Coin'),
    'tagline'    => env('LAUNDRY_TAGLINE', '[ISI TAGLINE TOKO]'),
    
    // Alamat dan Lokasi Outlet
    'city'             => env('LAUNDRY_CITY', '[ISI KOTA OUTLET]'),
    'address'          => env('LAUNDRY_ADDRESS', '[ISI ALAMAT LENGKAP OUTLET]'),
    'service_area'     => env('LAUNDRY_SERVICE_AREA', '[ISI NAMA AREA / JANGKAUAN LAYANAN]'),
    'google_maps_url'  => env('LAUNDRY_MAPS_URL', 'https://maps.google.com'),

    // Kontak Outlet
    'phone'      => env('LAUNDRY_PHONE', '[ISI NOMOR HP/TELEPON]'),
    'whatsapp'   => env('LAUNDRY_WHATSAPP', '[ISI NOMOR WHATSAPP]'), // format nomor: misal 628123456789
    'email'      => env('LAUNDRY_EMAIL', '[ISI EMAIL TOKO]'),

    // Metode pembayaran online (cashless) yang ditampilkan ke pelanggan.
    // key = nilai kolom payments.method, label = teks yang tampil di website.
    'online_payment_methods' => [
        'transfer' => [
            'label'       => 'Transfer Bank',
            'description' => 'Transfer ke rekening resmi outlet melalui m-banking, internet banking, atau ATM.',
        ],
        'e-wallet' => [
            'label'       => 'E-Wallet / QRIS',
            'description' => 'Scan QRIS atau bayar lewat aplikasi dompet digital dan m-banking yang mendukung QRIS.',
        ],
    ],

    // Jenis cucian untuk form pemesanan + batas kapasitas per mesin (sama dengan info kapasitas di landing page).
    // key = nilai cart_items.item_type, capacity/unit dipakai pembantu "saran jumlah mesin".
    'item_types' => [
        'pakaian'  => ['label' => 'Pakaian Biasa', 'capacity' => 6, 'unit' => 'KG'],
        'sprei'    => ['label' => 'Sprei / Selimut Tipis', 'capacity' => 3, 'unit' => 'KG'],
        'bedcover' => ['label' => 'Bedcover', 'capacity' => 1, 'unit' => 'Pcs'],
        // Campuran memakai batas paling aman agar mesin tidak kelebihan muatan
        'campuran' => ['label' => 'Campuran', 'capacity' => 3, 'unit' => 'KG'],
    ],

    // Pesanan online: rencana antar cucian ke outlet maksimal H+N
    'drop_off_max_days' => 7,

    // Batas keranjang agar 1 transaksi tetap wajar
    'cart_max_lines' => 10,
    'max_machine_qty' => 20,

    // Jam Operasional
    'operating_hours' => [
        'weekday'    => env('LAUNDRY_HOURS_WEEKDAY', '[ISI JAM OPERASIONAL SENIN-JUMAT]'),
        'weekend'    => env('LAUNDRY_HOURS_WEEKEND', '[ISI JAM OPERASIONAL SABTU-MINGGU]'),
        'timezone'   => env('LAUNDRY_TIMEZONE_LABEL', '[ISI ZONA WAKTU]'),
        'last_order' => env('LAUNDRY_LAST_ORDER', '[ISI JAM LAST ORDER]'),
        'notes'      => env('LAUNDRY_HOURS_NOTES', '[ISI CATATAN OPERASIONAL]'),
    ],
];
