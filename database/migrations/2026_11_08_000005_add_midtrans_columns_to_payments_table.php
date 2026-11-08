<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: kolom Midtrans Snap. Kolom lama dipakai ulang:
 * reference = order_id Midtrans ({kode}-P{n}), checkout_url = redirect_url Snap, meta = respons mentah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('snap_token', 100)->nullable()->after('checkout_url');
            // ID transaksi Midtrans; bisa berganti jika pelanggan mengganti metode di Snap (diisi di Tahap 5)
            $table->string('transaction_id', 100)->nullable()->unique()->after('snap_token');
            // payment_type asli Midtrans (bank_transfer, qris, gopay, ...); method tetap diisi dari pemetaannya
            $table->string('payment_type', 50)->nullable()->after('method');
            // amount_mismatch | duplicate_paid_needs_refund | paid_after_closed → halaman "Perlu Refund"
            $table->string('review_flag', 40)->nullable()->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
            $table->dropIndex(['review_flag']);
            $table->dropColumn(['snap_token', 'transaction_id', 'payment_type', 'review_flag']);
        });
    }
};
