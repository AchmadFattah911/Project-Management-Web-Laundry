<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kas harian per shift kasir. Satu shift aktif per pegawai dijaga UNIQUE pada kolom virtual
 * (hanya terisi saat status open), aman juga untuk klik "Buka Shift" paralel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['open', 'closed', 'verified'])->default('open');
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opening_balance');
            $table->timestamp('closed_at')->nullable();
            // Dihitung sistem saat tutup: saldo awal + tunai masuk - refund/void tunai
            $table->bigInteger('expected_cash')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->json('denominations')->nullable();
            $table->string('close_note', 500)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verify_note', 500)->nullable();
            $table->string('open_token', 64)->nullable()->unique();
            $table->string('close_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->unsignedBigInteger('active_owner')->nullable()
                ->virtualAs("CASE WHEN status = 'open' THEN user_id END");
            $table->unique('active_owner', 'cash_shifts_single_open_unique');
            $table->index(['status', 'opened_at']);
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('outlet_method')->constrained('cash_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::dropIfExists('cash_shifts');
    }
};
