<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Skema hasil migrate:fresh harus sama dengan yang dipakai aplikasi,
 * tanpa tabel/kolom sisa fitur lama (kurir, antar-jemput, alamat, berat, setrika, banner, ulasan).
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_dan_kolom_lama_tidak_ikut_terbuat(): void
    {
        foreach (['deliveries', 'order_items', 'user_addresses', 'coverage_areas', 'outlet_locations', 'banners', 'reviews'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tabel lama '{$table}' seharusnya tidak ada.");
        }

        $this->assertFalse(Schema::hasColumn('orders', 'weight'));
        $this->assertFalse(Schema::hasColumn('orders', 'pickup_address'));
        $this->assertFalse(Schema::hasColumn('orders', 'delivery_address'));
        $this->assertFalse(Schema::hasColumn('users', 'address'));
    }

    public function test_tabel_yang_dipakai_aplikasi_terbuat_lengkap(): void
    {
        $this->assertTrue(Schema::hasColumns('services', ['name', 'slug', 'category', 'unit', 'price', 'estimate', 'status', 'image', 'features', 'tag']));
        $this->assertTrue(Schema::hasColumns('orders', ['order_code', 'user_id', 'customer_name', 'customer_phone', 'service_id', 'machine_qty', 'unit_price', 'total_price', 'item_type', 'notes', 'status']));
        $this->assertTrue(Schema::hasColumns('orders', ['checkout_id']));
        $this->assertTrue(Schema::hasColumns('payments', ['order_id', 'checkout_id', 'amount', 'method', 'gateway', 'reference', 'idempotency_key', 'checkout_url', 'status', 'paid_at', 'expires_at', 'meta']));
        $this->assertTrue(Schema::hasColumns('checkouts', ['code', 'user_id', 'total_amount', 'status', 'contact_phone', 'drop_off_date', 'notes', 'submission_token']));
        $this->assertTrue(Schema::hasColumns('cart_items', ['user_id', 'service_id', 'qty', 'item_type', 'notes']));
        $this->assertTrue(Schema::hasColumns('idempotency_keys', ['key', 'user_id', 'scope', 'request_hash', 'status', 'payment_id', 'response', 'locked_until']));
        $this->assertTrue(Schema::hasColumns('payment_events', ['gateway', 'event_id', 'payment_id', 'status', 'payload']));
    }

    public function test_kunci_unik_penjaga_idempotency_ada_di_database(): void
    {
        $unique = fn (string $table) => collect(Schema::getIndexes($table))
            ->where('unique', true)
            ->map(fn ($i) => implode(',', $i['columns']))
            ->values()
            ->all();

        $this->assertContains('idempotency_key', $unique('payments'));
        $this->assertContains('reference', $unique('payments'));
        $this->assertContains('key', $unique('idempotency_keys'));
        $this->assertContains('gateway,event_id', $unique('payment_events'));
        $this->assertContains('submission_token', $unique('checkouts'));
        $this->assertContains('user_id,service_id,item_type', $unique('cart_items'));
    }

    public function test_setiap_migrasi_hanya_membuat_tabel_tanpa_rantai_ubah_hapus(): void
    {
        // Set migrasi dasar menulis skema final langsung di migrasi create (hasil konsolidasi).
        // Perubahan skema di masa depan boleh berupa migrasi alter baru setelah set dasar ini.
        $baseline = collect(glob(database_path('migrations/*.php')))
            ->filter(fn ($f) => basename($f) <= '2026_10_14_100008_create_payment_events_table.php');

        $this->assertCount(11, $baseline);

        foreach ($baseline as $file) {
            $code = file_get_contents($file);
            $up = substr($code, strpos($code, 'function up'), strpos($code, 'function down') - strpos($code, 'function up'));

            $this->assertStringNotContainsString('Schema::table(', $up, basename($file));
            $this->assertStringNotContainsString('ALTER TABLE', $up, basename($file));
            $this->assertStringNotContainsString('dropColumn', $up, basename($file));
        }
    }

    public function test_enum_sesuai_aturan_bisnis_terbaru(): void
    {
        $enum = fn (string $table, string $column) => DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column])->Type;

        $this->assertSame("enum('admin','pegawai','pelanggan')", $enum('users', 'role'));
        $this->assertSame("enum('pending','diproses','siap_diambil','selesai')", $enum('orders', 'status'));
        $this->assertSame("enum('cuci','kering','lipat')", $enum('order_processes', 'process'));
        $this->assertSame("enum('pending','paid','failed','expired')", $enum('payments', 'status'));
        $this->assertSame("enum('cash','transfer','e-wallet')", $enum('payments', 'method'));
    }

    public function test_setiap_catatan_migrasi_punya_file(): void
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $recorded = DB::table('migrations')->pluck('migration');

        $this->assertEmpty($recorded->diff($files)->all(), 'Ada catatan migrasi tanpa file.');
        $this->assertEmpty($files->diff($recorded)->all(), 'Ada file migrasi yang belum dijalankan.');
    }
}
