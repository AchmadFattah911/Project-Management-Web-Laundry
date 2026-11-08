<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pengecualian aturan aditif yang disetujui: HANYA menambah nilai ENUM 'dibatalkan' di akhir daftar.
 * Urutan & nilai lama tidak berubah, sehingga data lama tetap valid.
 * down() menolak jalan jika nilai baru sudah terpakai (rollback tidak boleh merusak data).
 */
return new class extends Migration
{
    private const OLD = "'menunggu_pembayaran','lunas','gagal','kadaluarsa'";

    public function up(): void
    {
        DB::statement('ALTER TABLE checkouts MODIFY status ENUM(' . self::OLD . ",'dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran'");
    }

    public function down(): void
    {
        $used = DB::table('checkouts')->where('status', 'dibatalkan')->count();

        if ($used > 0) {
            throw new RuntimeException("Rollback ditolak: {$used} transaksi berstatus 'dibatalkan'. Tangani data tersebut secara manual terlebih dahulu.");
        }

        DB::statement('ALTER TABLE checkouts MODIFY status ENUM(' . self::OLD . ") NOT NULL DEFAULT 'menunggu_pembayaran'");
    }
};
