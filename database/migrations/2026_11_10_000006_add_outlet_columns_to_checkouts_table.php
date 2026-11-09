<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi outlet memakai header checkouts yang sama dengan transaksi online (channel = outlet).
 * Kolom tambahan hanya terisi untuk outlet; transaksi online lama tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            // Petugas yang membuat transaksi outlet (NULL = dibuat pelanggan dari website)
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            // now = bayar sekarang | on_pickup = bayar saat ambil
            $table->string('payment_timing', 20)->nullable()->after('status');
            // Rencana metode bayar outlet: cash | qris | transfer
            $table->string('outlet_method', 20)->nullable()->after('payment_timing');
            $table->index(['channel', 'created_at'], 'checkouts_channel_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropIndex('checkouts_channel_created_index');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['payment_timing', 'outlet_method']);
        });
    }
};
