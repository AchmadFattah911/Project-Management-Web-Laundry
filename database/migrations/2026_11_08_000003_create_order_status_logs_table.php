<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: jejak setiap perubahan status transaksi (checkouts) dan pesanan (orders).
 * Satu baris mengisi salah satu dari checkout_id / order_id. Ditulis hanya oleh OrderStateMachine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            // NULL = baris pertama saat dibuat
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('note', 500)->nullable();
            // pelanggan | admin | pegawai | webhook | reconcile | scheduler | system
            $table->string('actor_type', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
