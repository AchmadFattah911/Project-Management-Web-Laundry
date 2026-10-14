<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesanan: 1 order = 1 layanan. Bisa dari pelanggan online (lewat checkout)
 * atau walk-in yang dicatat petugas di kasir (user_id boleh kosong).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_code', 16)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Data pelanggan walk-in (tanpa akun)
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('machine_qty')->default(1);
            // Harga disalin dari tarif layanan saat order dibuat, agar riwayat tidak berubah
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2);
            $table->string('item_type', 50)->nullable();
            $table->text('notes')->nullable();
            // pending → diproses → siap_diambil → selesai (sudah dilaundry & sudah diambil)
            $table->enum('status', ['pending', 'diproses', 'siap_diambil', 'selesai'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
