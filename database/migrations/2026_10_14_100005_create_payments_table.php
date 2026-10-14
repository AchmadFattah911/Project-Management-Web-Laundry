<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan / pembayaran. Menempel ke checkout (transaksi keranjang) ATAU ke 1 order
 * (order walk-in / order lama). Gateway: manual (kasir), dummy, atau gateway asli nanti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained()->cascadeOnDelete();
            // 12 digit agar sama dengan checkouts.total_amount (tagihan 1 transaksi berisi beberapa order)
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'transfer', 'e-wallet']);
            $table->string('gateway', 30)->default('manual');
            // ID transaksi dari gateway, dipakai mencocokkan notifikasi webhook
            $table->string('reference', 100)->nullable()->unique();
            // 1 request bayar = 1 key unik; key yang sama selalu memakai tagihan yang sama
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->string('checkout_url', 2048)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
