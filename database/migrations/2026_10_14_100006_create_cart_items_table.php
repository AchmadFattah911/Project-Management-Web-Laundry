<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keranjang pelanggan sebelum checkout. Harga sengaja tidak disimpan (selalu dari tarif terbaru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->string('item_type', 30);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'service_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
