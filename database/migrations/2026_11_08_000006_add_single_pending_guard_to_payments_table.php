<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: database menolak lebih dari 1 payment 'pending' per transaksi (checkout) atau per order tunggal.
 *
 * pending_owner = 'c:{checkout_id}' / 'o:{order_id}' selama status pending, NULL selain itu
 * (UNIQUE mengizinkan banyak NULL, jadi hanya baris pending yang dibatasi).
 *
 * Sengaja VIRTUAL, bukan STORED: MySQL menolak STORED generated column yang dasarnya kolom
 * ber-FK ON DELETE CASCADE (payments.checkout_id / order_id), dan FK tersebut tidak boleh diubah.
 * Sudah diuji di DB testing (MySQL 8.0.30): VIRTUAL + UNIQUE index berjalan dengan FK CASCADE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pengaman: jangan membuat UNIQUE di atas data yang sudah melanggar
        $duplicates = DB::table('payments')
            ->selectRaw("CONCAT(IF(checkout_id IS NULL, 'o:', 'c:'), COALESCE(checkout_id, order_id)) AS owner, COUNT(*) AS n")
            ->where('status', 'pending')
            ->groupBy('owner')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'owner');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Migrasi dibatalkan: ada lebih dari 1 payment pending untuk ' . $duplicates->keys()->implode(', ') . '. Rapikan data tersebut dulu.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->string('pending_owner', 30)->nullable()
                ->virtualAs("CASE WHEN status = 'pending' THEN CONCAT(IF(checkout_id IS NULL, 'o:', 'c:'), COALESCE(checkout_id, order_id)) END");
            $table->unique('pending_owner', 'payments_single_pending_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_single_pending_unique');
            $table->dropColumn('pending_owner');
        });
    }
};
