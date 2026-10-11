<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('category')->default('Kiloan')->after('name');
            $table->string('estimate')->default('1-2 Hari')->after('price');
            $table->enum('status', ['tersedia', 'sibuk', 'tidak_tersedia'])->default('tersedia')->after('estimate');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['category', 'estimate', 'status']);
        });
    }
};
