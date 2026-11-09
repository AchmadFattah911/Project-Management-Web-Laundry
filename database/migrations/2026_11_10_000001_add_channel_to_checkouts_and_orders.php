<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saluran transaksi (online / outlet), selalu ditentukan sistem, tidak pernah dari input.
 *
 * Backfill:
 *   - checkout lama = online (semua checkout dibuat dari keranjang website);
 *   - order dari checkout = online;
 *   - order tunggal lama = online jika dibuat pelanggan (log status pertama oleh pelanggan) atau
 *     pernah ditagih lewat gateway (bukan manual); selain itu walk-in kasir = outlet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->enum('channel', ['online', 'outlet'])->default('online')->after('code');
            $table->index(['channel', 'status'], 'checkouts_channel_status_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('channel', ['online', 'outlet'])->default('outlet')->after('checkout_id');
            $table->index('channel');
        });

        DB::table('orders')->whereNotNull('checkout_id')->update(['channel' => 'online']);

        DB::table('orders')
            ->whereNull('checkout_id')
            ->where(function ($q) {
                $q->whereExists(fn ($s) => $s->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->where('payments.gateway', '!=', 'manual'))
                  ->orWhereExists(fn ($s) => $s->from('order_status_logs')
                    ->whereColumn('order_status_logs.order_id', 'orders.id')
                    ->whereNull('order_status_logs.from_status')
                    ->where('order_status_logs.actor_type', 'pelanggan'));
            })
            ->update(['channel' => 'online']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropIndex('checkouts_channel_status_index');
            $table->dropColumn('channel');
        });
    }
};
