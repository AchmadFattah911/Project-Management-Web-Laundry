<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaksi pelanggan dari keranjang: 1 checkout = beberapa order (1 order per layanan),
 * dibayar sekali lewat 1 tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['menunggu_pembayaran', 'lunas', 'gagal', 'kadaluarsa'])->default('menunggu_pembayaran');
            $table->string('contact_phone', 25);
            $table->date('drop_off_date')->nullable();
            $table->text('notes')->nullable();
            // Token form konfirmasi: klik submit ganda tetap menghasilkan 1 checkout
            $table->string('submission_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
