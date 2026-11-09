<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan void transaksi outlet: pegawai mengajukan (alasan wajib), admin menyetujui/menolak.
 * Transaksi asli tidak diubah; void yang disetujui = entri void_reversal di ledger.
 * Maksimal 1 pengajuan pending dan 1 void disetujui per transaksi (UNIQUE pada kolom virtual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('void_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Nominal yang dibalik saat disetujui (0 untuk transaksi yang belum dibayar)
            $table->unsignedBigInteger('amount')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->string('submission_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->unsignedBigInteger('open_guard')->nullable()
                ->virtualAs("CASE WHEN status IN ('pending', 'approved') THEN checkout_id END");
            $table->unique('open_guard', 'void_requests_single_open_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('void_requests');
    }
};
