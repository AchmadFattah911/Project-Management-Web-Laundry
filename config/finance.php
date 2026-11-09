<?php

/*
|--------------------------------------------------------------------------
| Keuangan: ledger, pembatalan online, biaya gateway, shift, pengeluaran, export
|--------------------------------------------------------------------------
| Semua nominal integer rupiah. Persentase ditulis dalam basis poin (1% = 100 bps) agar
| tidak ada float di perhitungan.
*/

return [
    // Pelanggan boleh membatalkan transaksi online yang sudah lunas selama ini (menit) sejak paid_at,
    // dan selama semua item masih 'pending' (cucian belum diterima outlet).
    'online_cancel_window_minutes' => (int) env('ONLINE_CANCEL_WINDOW_MINUTES', 15),

    /*
    | Estimasi biaya gateway per payment_type Midtrans (ditampilkan berlabel "estimasi").
    | fee = flat + round(amount × bps / 10000). Sesuaikan dengan kontrak MDR outlet.
    */
    'gateway_fees' => [
        'bank_transfer' => ['flat' => 4000, 'bps' => 0],
        'echannel'      => ['flat' => 4000, 'bps' => 0],
        'permata'       => ['flat' => 4000, 'bps' => 0],
        'qris'          => ['flat' => 0, 'bps' => 70],
        'gopay'         => ['flat' => 0, 'bps' => 200],
        'shopeepay'     => ['flat' => 0, 'bps' => 200],
        'credit_card'   => ['flat' => 2000, 'bps' => 290],
        'default'       => ['flat' => 0, 'bps' => 0],
    ],

    // payment_type Midtrans yang didukung Refund API (docs.midtrans.com/reference/refund-transaction).
    // Selain ini (VA / bank transfer / echannel) refund lewat transfer manual oleh admin.
    'midtrans_refundable_types' => ['credit_card', 'gopay', 'shopeepay', 'dana', 'ovo', 'qris', 'kredivo', 'akulaku'],

    // Metode bayar transaksi outlet: key = nilai payment_method di ledger, method = payments.method
    'outlet_methods' => [
        'cash'     => ['label' => 'Tunai', 'method' => 'cash'],
        'qris'     => ['label' => 'QRIS', 'method' => 'e-wallet'],
        'transfer' => ['label' => 'Transfer Bank', 'method' => 'transfer'],
    ],

    // Pecahan uang untuk hitung laci saat tutup shift
    'denominations' => [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100],

    'expense' => [
        'receipt_max_kb' => (int) env('EXPENSE_RECEIPT_MAX_KB', 4096),
        'receipt_mimes'  => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'disk'           => env('EXPENSE_RECEIPT_DISK', 'local'),
    ],

    'export' => [
        // Rentang dengan entri ledger lebih dari ini diproses lewat queue (halaman "Ekspor Saya")
        'sync_row_limit' => (int) env('FINANCE_EXPORT_SYNC_ROWS', 5000),
        'disk'           => env('FINANCE_EXPORT_DISK', 'local'),
    ],
];
