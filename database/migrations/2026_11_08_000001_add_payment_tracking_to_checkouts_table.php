<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: batas waktu bayar, waktu lunas, dan alasan transaksi masih menunggu pembayaran.
 * Semua kolom nullable, jadi transaksi lama tetap valid tanpa perlu diisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->timestamp('payment_deadline')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_deadline');
            // menunggu_transfer | popup_ditutup | ditolak | kedaluwarsa (lihat Checkout::PENDING_REASONS)
            $table->string('pending_reason', 30)->nullable()->after('paid_at');

            // Dipakai job kedaluwarsa: cari transaksi menunggu yang lewat batas waktu
            $table->index(['status', 'payment_deadline'], 'checkouts_status_deadline_index');
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropIndex('checkouts_status_deadline_index');
            $table->dropColumn(['payment_deadline', 'paid_at', 'pending_reason']);
        });
    }
};
