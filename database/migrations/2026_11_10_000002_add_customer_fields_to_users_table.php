<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data pelanggan: nomor HP ternormalisasi (+62, unik), status aktif (tidak ada hapus permanen),
 * asal akun (register website / dibuat di outlet), dan jejak penggabungan akun ganda.
 *
 * Backfill phone_e164 hanya untuk nomor yang valid dan belum dipakai akun lain (id terkecil menang);
 * nomor ganda/tidak valid dibiarkan NULL dan dicatat di log untuk dirapikan lewat menu gabung pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_e164', 20)->nullable()->unique()->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            // register | outlet
            $table->string('created_via', 20)->nullable()->after('deactivated_at');
            $table->foreignId('merged_into_id')->nullable()->after('created_via')->constrained('users')->nullOnDelete();
            $table->text('customer_notes')->nullable()->after('merged_into_id');
        });

        $taken = [];
        $skipped = [];

        DB::table('users')->whereNotNull('phone')->orderBy('id')->select(['id', 'phone'])->each(function ($user) use (&$taken, &$skipped) {
            $normalized = PhoneNumber::tryNormalize($user->phone);

            if ($normalized === null || isset($taken[$normalized])) {
                $skipped[] = $user->id;

                return;
            }

            $taken[$normalized] = true;
            DB::table('users')->where('id', $user->id)->update(['phone_e164' => $normalized]);
        });

        if ($skipped !== []) {
            Log::warning('users.phone_e164 tidak di-backfill (nomor tidak valid / ganda)', ['user_ids' => $skipped]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropUnique(['phone_e164']);
            $table->dropColumn(['phone_e164', 'is_active', 'deactivated_at', 'created_via', 'customer_notes']);
        });
    }
};
