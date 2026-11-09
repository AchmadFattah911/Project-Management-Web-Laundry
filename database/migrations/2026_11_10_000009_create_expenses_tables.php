<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeluaran operasional: pegawai input dengan foto nota (storage private), admin approve/reject.
 * Hanya yang approved masuk ledger sebagai expense.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('expense_date');
            $table->string('description', 1000);
            $table->string('receipt_path', 255);
            $table->string('receipt_name', 255)->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->string('submission_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'expense_date']);
            $table->index(['created_by', 'expense_date']);
        });

        $now = now();
        DB::table('expense_categories')->insert(array_map(fn ($name) => ['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], [
            'Deterjen & Pewangi', 'Listrik', 'Air', 'Gas', 'Perawatan Mesin', 'Plastik & Kemasan', 'Gaji / Upah Harian', 'Lain-lain',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
