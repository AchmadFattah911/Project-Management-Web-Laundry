<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog layanan. Self Service = dipakai langsung di outlet,
 * Non-Self Service = drop-off yang bisa dipesan online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 50)->default('Self Service');
            $table->string('unit', 50)->default('Mesin');
            $table->decimal('price', 10, 2);
            $table->string('estimate')->default('1-2 Hari');
            $table->enum('status', ['tersedia', 'sibuk', 'tidak_tersedia'])->default('tersedia');
            $table->text('description')->nullable();
            // Tampilan katalog / landing page (dikelola admin)
            $table->string('image')->nullable();
            $table->json('features')->nullable();
            $table->string('tag')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
