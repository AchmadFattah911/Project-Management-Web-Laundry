<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: setiap notifikasi yang masuk ke webhook disimpan apa adanya SEBELUM diverifikasi
 * (termasuk yang signature-nya salah), untuk audit dan penelusuran. Bukan sumber kebenaran status:
 * status pembayaran selalu dikonfirmasi ulang ke API status Midtrans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            // Isi payload apa adanya (belum terverifikasi); kolom di bawah hanya salinan untuk pencarian
            $table->string('order_id', 100)->nullable()->index();
            $table->string('transaction_id', 100)->nullable()->index();
            $table->string('transaction_status', 30)->nullable();
            $table->string('fraud_status', 30)->nullable();
            $table->string('gross_amount', 30)->nullable();
            $table->boolean('signature_valid')->nullable();
            // Hasil pemrosesan: applied, duplicate, invalid_signature, unknown_reference, amount_mismatch, ...
            $table->string('result', 40)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->mediumText('raw_body');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
    }
};
