<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('type', ['pickup', 'delivery'])->default('pickup')->after('status');
            $table->timestamp('scheduled_at')->nullable()->after('type');
            $table->text('notes')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['type', 'scheduled_at', 'notes']);
        });
    }
};
