<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEntry;
use App\Models\Refund;
use App\Models\User;
use App\Models\VoidRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFinanceData;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 9: alur keuangan lengkap → ledger:verify 0 selisih; otorisasi pegawai terpusat.
 */
class FinanceEndToEndTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, CreatesFinanceData;

    private User $pegawai;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
    }

    public function test_semua_jenis_entri_tercatat_dan_ledger_verify_nol_selisih(): void
    {
        $customer = $this->makeUser();
        $service = $this->makeService(attributes: ['price' => 15000]);
        $this->openShift($this->pegawai);

        // sale outlet tunai + void
        $voided = $this->createOutlet($this->pegawai, $this->outletPayload($customer, $service));
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $voided), ['reason' => 'salah input layanan pelanggan', 'submission_token' => (string) Str::uuid()]);
        $this->actingAs($this->admin)->post(route('panel.void.approve', VoidRequest::sole()));

        // sale outlet bayar saat ambil → lunas
        $pickup = $this->createOutlet($this->pegawai, $this->outletPayload($customer, $service, ['payment_timing' => 'on_pickup']));
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.settle', $pickup), ['payment_method' => 'transfer', 'payment_reference' => 'TRF-1', 'submission_token' => (string) Str::uuid()]);

        // sale online + biaya gateway + refund manual selesai
        $online = $this->onlineCheckout($customer, $service, payment: ['payment_type' => 'bank_transfer']);
        $this->actingAs($customer)->post(route('pelanggan.transaksi.cancel', $online), ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi']);
        $this->actingAs($this->admin)->post(route('admin.refunds.cancellation.complete', Refund::sole()), ['proof' => UploadedFile::fake()->image('b.png'), 'note' => 'Transfer BCA ref 1']);

        // pengeluaran disetujui
        $category = ExpenseCategory::firstOrCreate(['name' => 'Uji E2E'], ['is_active' => true]);
        $this->actingAs($this->pegawai)->post(route('panel.pengeluaran.store'), [
            'category_id' => $category->id, 'amount' => 5000, 'expense_date' => now('Asia/Jakarta')->toDateString(),
            'description' => 'Beli plastik', 'receipt' => UploadedFile::fake()->image('n.jpg'), 'submission_token' => (string) Str::uuid(),
        ]);
        $this->actingAs($this->admin)->post(route('panel.pengeluaran.approve', Expense::sole()));

        $this->assertEqualsCanonicalizing(
            ['sale', 'void_reversal', 'refund', 'expense', 'gateway_fee'],
            FinancialEntry::distinct()->pluck('entry_type')->all(),
        );
        $this->artisan('ledger:verify')->expectsOutputToContain('0 selisih')->assertSuccessful();
    }

    public function test_pegawai_tidak_bisa_mengakses_laporan_persetujuan_dan_export(): void
    {
        $this->actingAs($this->pegawai);

        $this->get(route('panel.laporan.index'))->assertForbidden();
        $this->get(route('panel.ekspor.index'))->assertForbidden();
        $this->post(route('panel.ekspor.store'))->assertForbidden();
        $this->get(route('panel.void.index'))->assertForbidden();
        $this->get(route('panel.periode.index'))->assertForbidden();
        $this->post(route('panel.periode.store'), ['period_start' => '2026-01-01', 'period_end' => '2026-01-31'])->assertForbidden();
        $this->post(route('panel.pelanggan.merge'))->assertForbidden();
        $this->get(route('panel.transaksi.index', ['tab' => 'aplikasi', 'audit' => 1]))->assertForbidden();
        $this->get(route('admin.refunds.index'))->assertForbidden();

        // Yang memang boleh untuk pegawai
        $this->get(route('panel.transaksi.index'))->assertOk();
        $this->get(route('panel.shift.index'))->assertOk();
        $this->get(route('panel.pengeluaran.index'))->assertOk();
        $this->get(route('panel.pelanggan.index'))->assertOk();
    }

    public function test_halaman_panel_admin_terbuka(): void
    {
        $this->actingAs($this->admin);

        foreach (['panel.transaksi.index', 'panel.transaksi.create', 'panel.pelanggan.index', 'panel.shift.index', 'panel.pengeluaran.index',
            'panel.void.index', 'panel.laporan.index', 'panel.ekspor.index', 'panel.periode.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
