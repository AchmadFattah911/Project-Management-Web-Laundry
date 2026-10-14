<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency key request pembayaran: UNIQUE(key) menjamin key yang sama hanya diproses 1x.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 50);
            // Hash isi request: key yang sama dengan isi berbeda ditolak
            $table->char('request_hash', 64);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('response')->nullable();
            // Klaim "processing" yang macet (proses mati di tengah jalan) boleh diambil alih setelah lewat
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
