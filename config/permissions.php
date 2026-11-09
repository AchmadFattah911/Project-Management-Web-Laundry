<?php

/*
|--------------------------------------------------------------------------
| Peta Permission per Role
|--------------------------------------------------------------------------
| Satu-satunya tempat hak akses per aksi ditentukan. Setiap permission didaftarkan sebagai
| Gate native Laravel (lihat AppServiceProvider), dipakai lewat middleware `can:...`,
| $user->can(...), atau Gate::authorize(...). Akun nonaktif tidak punya permission apa pun.
*/

$pegawai = [
    // Data pelanggan
    'pelanggan.view',
    'pelanggan.create',
    'pelanggan.update',

    // Transaksi outlet & aplikasi
    'transaksi.outlet.view',
    'transaksi.outlet.create',
    'transaksi.outlet.update',
    'transaksi.outlet.settle',
    'transaksi.online.view',
    'transaksi.status.update',
    'transaksi.void.request',

    // Kas harian / shift milik sendiri
    'shift.open',
    'shift.close',
    'shift.view_own',

    // Pengeluaran operasional milik sendiri
    'pengeluaran.create',
    'pengeluaran.view_own',
];

return [
    'roles' => [
        'pegawai' => $pegawai,

        'admin' => array_merge($pegawai, [
            'pelanggan.deactivate',
            'pelanggan.merge',
            'transaksi.void.approve',
            // Transaksi online yang belum/tidak lunas (read-only)
            'transaksi.audit_pembayaran.view',
            'refund.process',
            'shift.view_all',
            'shift.verify',
            'pengeluaran.view_all',
            'pengeluaran.approve',
            'pengeluaran.kategori.manage',
            'laporan.view',
            'laporan.export',
            'periode.close',
            'ledger.review',
            'audit.view',
        ]),

        // Pelanggan memakai policy per transaksi miliknya (CheckoutPolicy/OrderPolicy), bukan permission panel
        'pelanggan' => [],
    ],
];
