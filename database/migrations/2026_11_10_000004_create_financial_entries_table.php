<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger keuangan append-only. Laporan hanya membaca tabel ini.
 * amount bertanda (integer rupiah): sale positif; void_reversal, refund, expense, gateway_fee negatif.
 * UNIQUE(entry_type, source_type, source_id) = 1 kejadian sumber hanya tercatat 1x (aman untuk retry/backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('entry_type', ['sale', 'void_reversal', 'refund', 'expense', 'gateway_fee']);
            // NULL untuk pengeluaran (tidak terikat saluran)
            $table->enum('channel', ['outlet', 'online'])->nullable();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->bigInteger('amount');
            $table->string('payment_method', 30)->nullable();
            $table->timestamp('occurred_at');
            $table->date('business_date');
            $table->unsignedBigInteger('shift_id')->nullable();
            // Transaksi terkait (untuk kode transaksi & pelanggan di laporan/export)
            $table->foreignId('checkout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['entry_type', 'source_type', 'source_id'], 'financial_entries_source_unique');
            // Agregasi laporan: GROUP BY business_date per saluran/jenis
            $table->index(['business_date', 'channel', 'entry_type'], 'financial_entries_report_index');
            $table->index(['business_date', 'payment_method'], 'financial_entries_method_index');
            $table->index('shift_id');
        });

        // Temuan ledger:verify yang perlu ditinjau manusia (ledger sendiri tidak pernah diubah)
        Schema::create('ledger_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('issue', 60);
            $table->json('detail')->nullable();
            // perlu_tinjau | selesai
            $table->string('status', 20)->default('perlu_tinjau');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'issue'], 'ledger_reviews_source_issue_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reviews');
        Schema::dropIfExists('financial_entries');
    }
};
