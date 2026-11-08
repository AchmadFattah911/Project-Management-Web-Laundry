<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditif: nama layanan disalin saat order dibuat (pasangan unit_price), agar nama layanan yang
 * diubah admin di kemudian hari tidak mengubah riwayat pesanan. Order lama dibiarkan NULL
 * dan ditampilkan memakai nama layanan saat ini (lihat Order::serviceName()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('service_name_snapshot')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('service_name_snapshot');
        });
    }
};
