<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatalan transaksi online setelah bayar + refund.
 *
 *   - checkouts.refund_status (none/diproses/selesai/gagal); status checkout tetap 'lunas' (final).
 *   - orders.status bertambah 'dibatalkan' (nilai ENUM ditambah di akhir).
 *   - refunds: 1 refund per transaksi (UNIQUE checkout_id) → klik batal paralel tetap 1 refund.
 *     refund_key UNIQUE dipakai ulang saat retry Refund API Midtrans (idempoten di sisi Midtrans).
 */
return new class extends Migration
{
    private const ORDER_STATUSES = "'pending','diproses','siap_diambil','selesai'";

    public function up(): void
    {
        DB::statement('ALTER TABLE orders MODIFY status ENUM(' . self::ORDER_STATUSES . ",'dibatalkan') NOT NULL DEFAULT 'pending'");

        Schema::table('checkouts', function (Blueprint $table) {
            $table->enum('refund_status', ['none', 'diproses', 'selesai', 'gagal'])->default('none')->after('status');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            // midtrans_api | manual (transfer oleh admin)
            $table->enum('method', ['midtrans_api', 'manual']);
            $table->enum('status', ['diproses', 'selesai', 'gagal'])->default('diproses');
            $table->string('refund_key', 64)->unique();
            $table->string('reason', 500)->nullable();
            // Rekening tujuan refund manual (diisi pelanggan); nomor rekening disimpan terenkripsi
            $table->string('bank_name', 100)->nullable();
            $table->text('account_number')->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proof_path', 255)->nullable();
            $table->string('note', 1000)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('refund_status');
        });

        // Menolak rollback jika sudah ada pesanan berstatus dibatalkan (data tidak boleh hilang diam-diam)
        if (DB::table('orders')->where('status', 'dibatalkan')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: ada pesanan berstatus dibatalkan.');
        }

        DB::statement('ALTER TABLE orders MODIFY status ENUM(' . self::ORDER_STATUSES . ") NOT NULL DEFAULT 'pending'");
    }
};
