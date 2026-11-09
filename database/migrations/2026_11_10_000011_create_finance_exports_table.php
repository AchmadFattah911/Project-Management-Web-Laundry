<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat export Excel laporan keuangan ("Ekspor Saya"). Rentang besar dibuat lewat queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('filters');
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->string('path', 255)->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_exports');
    }
};
