<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahapan pengerjaan layanan Non-Self Service (cuci → kering → lipat), dicatat per petugas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('process', ['cuci', 'kering', 'lipat']);
            $table->enum('status', ['pending', 'selesai'])->default('pending');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_processes');
    }
};
