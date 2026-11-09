# Transaksi & Keuangan

Fitur kasir outlet, kas harian, pengeluaran, pembatalan online + refund, laporan keuangan, dan export Excel.
Semua uang integer rupiah (tidak ada float). Zona waktu bisnis: `config('laundry.timezone')` (`LAUNDRY_TIMEZONE`,
bawaan `Asia/Jakarta`); waktu disimpan UTC, pengelompokan memakai **tanggal bisnis**. Minggu = Senin s.d. Minggu (ISO).

---

## 1. Saluran transaksi

| Saluran | Dibuat dari | Header | Kode |
|---|---|---|---|
| `outlet` | Panel kasir (`/panel/transaksi/baru`) | `checkouts.channel = outlet` | `OTL-XXXXXXXX` |
| `online` | Checkout keranjang website | `checkouts.channel = online` | `TRX-XXXXXXXX` |

Saluran **selalu ditentukan sistem** (`OutletTransactionService` / `CheckoutService`). Field `channel` yang dikirim
lewat request tidak pernah masuk `validated()` dan diabaikan. `orders.channel` mengikuti header-nya.

Backfill (migration `2026_11_10_000001`): checkout lama = `online`; order tunggal lama = `online` bila dibuat pelanggan
atau ditagih lewat gateway, selain itu walk-in kasir = `outlet`. Walk-in lama (tanpa header) tetap terbaca di halaman
pesanan pegawai lama dan detail pelanggan.

Pelanggan walk-in disimpan sebagai akun pelanggan (`users`, `created_via = outlet`) dengan email placeholder
`*.invalid` dan password acak (tidak bisa login). Nomor HP dinormalisasi ke `+62…` dan unik (`users.phone_e164`).

## 2. Aturan pengakuan pendapatan (ledger)

Laporan **hanya** membaca `financial_entries` (append-only). Entri dibuat di transaksi DB yang sama dengan kejadiannya.

| entry_type | Kapan | amount | Tanggal bisnis |
|---|---|---|---|
| `sale` | Pembayaran berstatus paid (webhook, Midtrans, kasir, pelunasan "bayar saat ambil", lunas manual admin) | + | Tanggal bayar; jika periodenya sudah tutup buku → hari ini |
| `gateway_fee` | Bersama `sale` pembayaran Midtrans; **estimasi** `flat + amount×bps/10000` (`config/finance.php`) | − | Sama dengan sale |
| `void_reversal` | Void disetujui admin (transaksi outlet lunas) | − | Hari persetujuan (ditolak bila sudah tutup buku) |
| `refund` | Refund pembatalan online **selesai** | − | Hari selesai (manual: ditolak bila tutup buku) |
| `expense` | Pengeluaran disetujui admin | − | Tanggal pengeluaran (ditolak bila tutup buku) |

`UNIQUE(entry_type, source_type, source_id)`: retry/webhook ganda/backfill tidak menggandakan entri.
Neto = bruto + void + refund + biaya gateway. Laba = neto + pengeluaran (pengeluaran hanya di tab Gabungan).

## 3. Permission per role

Peta tunggal: `config/permissions.php` → setiap permission menjadi `Gate` native (dipakai via `can:` di route).
Akun nonaktif tidak punya permission dan tidak bisa login.

| Permission | Pegawai | Admin |
|---|:-:|:-:|
| `pelanggan.view/create/update` | ✓ | ✓ |
| `pelanggan.deactivate`, `pelanggan.merge` | | ✓ |
| `transaksi.outlet.view/create/update/settle`, `transaksi.online.view`, `transaksi.status.update`, `transaksi.void.request` | ✓ | ✓ |
| `transaksi.void.approve`, `transaksi.audit_pembayaran.view`, `refund.process` | | ✓ |
| `shift.open/close/view_own` | ✓ | ✓ |
| `shift.view_all`, `shift.verify` | | ✓ |
| `pengeluaran.create/view_own` | ✓ | ✓ |
| `pengeluaran.view_all/approve/kategori.manage` | | ✓ |
| `laporan.view`, `laporan.export`, `periode.close`, `ledger.review`, `audit.view` | | ✓ |

Tab **Aplikasi** hanya menampilkan transaksi online **lunas**; yang belum/tidak lunas hanya lewat "Audit pembayaran"
(admin, read-only). URL lama `/pegawai/keuangan` dialihkan ke ringkasan shift; `/pegawai/keuangan/export` → 403.

## 4. Alur utama

- **Transaksi outlet**: bayar sekarang (tunai wajib shift aktif) atau bayar saat ambil (boleh dikerjakan, status
  `selesai` hanya setelah lunas). Edit hanya sebelum lunas. Koreksi setelah lunas = **void**.
- **Void**: pegawai mengajukan (alasan ≥ 10 karakter) → admin setuju/tolak. Transaksi asli tidak diubah.
- **Shift**: seharusnya = saldo awal + Σ entri ledger tunai yang terikat shift (penjualan tunai − void/refund tunai).
  Satu shift aktif per petugas (UNIQUE virtual `active_owner`). Admin memverifikasi.
- **Pengeluaran**: foto nota wajib (jpg/png/webp/pdf, maks. `EXPENSE_RECEIPT_MAX_KB`), disimpan di disk private.
- **Pembatalan online setelah bayar**: semua item `pending` dan ≤ `ONLINE_CANCEL_WINDOW_MINUTES` (15) sejak `paid_at`.
  Checkout tetap `lunas`, `refund_status` diproses → selesai/gagal, item → `dibatalkan`.
  Refund API Midtrans untuk `credit_card, gopay, shopeepay, dana, ovo, qris, kredivo, akulaku`
  (sumber: docs.midtrans.com/reference/refund-transaction); VA/bank transfer → transfer manual di halaman *Perlu Refund*.
  API dipanggil di luar lock, `refund_key` sama saat dicoba ulang; hasil dikonfirmasi respons, API status (406), atau
  webhook `transaction_status=refund`.

## 5. Tutup buku

Admin mengunci rentang tanggal bisnis di `/panel/tutup-buku` (`closed_periods`, tidak bisa dibuka dari aplikasi).
Void, refund manual, dan pengeluaran yang jatuh di periode terkunci ditolak. Uang yang benar-benar masuk (sale dari
gateway) tidak ditolak: diakui di tanggal bisnis hari ini.

## 6. Backfill & verify

```bash
php artisan ledger:backfill --dry-run   # hitung pembayaran lunas tanpa entri ledger
php artisan ledger:backfill             # idempoten, aman diulang
php artisan ledger:verify               # 0 selisih = exit 0; selisih → tabel + ledger_reviews (perlu_tinjau), exit 1
```

`ledger:verify` memeriksa: setiap payment paid punya tepat 1 sale bernominal & bersaluran sama (+ biaya gateway
Midtrans), tidak ada sale yatim, dan void disetujui / refund selesai / pengeluaran disetujui ⇔ entri ledger −amount.
Ledger tidak pernah diubah; temuan ditinjau di `ledger_reviews`.

## 7. Laporan & export

- `/panel/laporan`: tab Gabungan/Outlet/Online; periode Harian/Mingguan/Bulanan + rentang kustom; agregasi
  `GROUP BY business_date` di database; bucket kosong = 0.
- Export Excel (openspout): sheet Info, Ringkasan, Outlet, Online, Refund & Void, Pengeluaran, Kontrol.
  Angka = NUMBER berformat Rp, tanggal = DATE, header dibekukan + autofilter, teks diawali `= + - @` di-escape.
  Sheet **Kontrol** wajib selisih 0; jika tidak, export gagal (dicatat di log + `audit_logs`), file tidak diterbitkan.
- Rentang dengan entri > `FINANCE_EXPORT_SYNC_ROWS` (5000) diproses lewat queue (`php artisan queue:work`) dan
  diunduh dari `/panel/ekspor` (hanya pemilik). Setiap export tercatat di `audit_logs`.

## 8. Audit log

`audit_logs` append-only (model menolak update/delete; tidak ada route ubah/hapus). Dicatat untuk create/update/void/
refund/approve/export/merge, termasuk aktor, role, before/after, alasan, IP, dan user agent.

## 9. Variabel `.env`

| Key | Bawaan | Arti |
|---|---|---|
| `LAUNDRY_TIMEZONE` | `Asia/Jakarta` | Zona waktu bisnis |
| `LAUNDRY_STAFF_WHATSAPP` | (WA outlet) | Nomor WA pegawai di nota pelanggan |
| `ONLINE_CANCEL_WINDOW_MINUTES` | `15` | Jendela pembatalan setelah bayar |
| `FINANCE_EXPORT_SYNC_ROWS` | `5000` | Batas entri export langsung; di atasnya lewat queue |
| `EXPENSE_RECEIPT_MAX_KB` | `4096` | Ukuran maksimum foto nota |
