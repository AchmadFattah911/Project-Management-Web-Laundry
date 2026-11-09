<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ClosedPeriod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 5: pengeluaran operasional.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pegawai;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
        // Jangan bergantung pada kategori bawaan migration (test konkurensi lain mengosongkan tabel)
        ExpenseCategory::firstOrCreate(['name' => 'Deterjen Uji'], ['is_active' => true]);
    }

    private function submit(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->pegawai)->post(route('panel.pengeluaran.store'), array_replace([
            'category_id'      => ExpenseCategory::where('name', 'Deterjen Uji')->value('id'),
            'amount'           => 75000,
            'expense_date'     => now('Asia/Jakarta')->toDateString(),
            'description'      => 'Beli deterjen 5 liter',
            'receipt'          => UploadedFile::fake()->image('nota.jpg', 600, 800),
            'submission_token' => (string) Str::uuid(),
        ], $overrides));
    }

    public function test_pegawai_mencatat_pengeluaran_dengan_nota_di_storage_private(): void
    {
        $this->submit()->assertSessionHas('success');

        $expense = Expense::sole();
        $this->assertSame([Expense::STATUS_PENDING, 75000, $this->pegawai->id], [$expense->status, $expense->amount, $expense->created_by]);
        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->assertStringStartsWith('expenses/', $expense->receipt_path);
        $this->assertSame(0, FinancialEntry::count(), 'Belum disetujui → belum masuk ledger.');
        $this->assertTrue(AuditLog::where('action', 'pengeluaran.create')->exists());
    }

    public function test_nota_wajib_dan_tipe_serta_ukuran_divalidasi(): void
    {
        $this->submit(['receipt' => null])->assertSessionHasErrors('receipt');
        $this->submit(['receipt' => UploadedFile::fake()->create('nota.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('receipt');
        $this->submit(['receipt' => UploadedFile::fake()->create('nota.pdf', 5000, 'application/pdf')])->assertSessionHasErrors('receipt');
        $this->submit(['amount' => 0])->assertSessionHasErrors('amount');
        $this->submit(['expense_date' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('expense_date');

        $this->assertSame(0, Expense::count());
    }

    public function test_submit_ganda_satu_pengeluaran(): void
    {
        $token = (string) Str::uuid();
        $this->submit(['submission_token' => $token]);
        $this->submit(['submission_token' => $token]);

        $this->assertSame(1, Expense::count());
    }

    public function test_hanya_yang_disetujui_masuk_ledger(): void
    {
        $this->submit();
        $this->submit(['amount' => 20000, 'description' => 'Beli plastik']);
        [$approved, $rejected] = Expense::orderBy('id')->get();

        $this->actingAs($this->pegawai)->post(route('panel.pengeluaran.approve', $approved))->assertForbidden();

        $this->actingAs($this->admin)->post(route('panel.pengeluaran.approve', $approved), ['note' => 'ok'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('panel.pengeluaran.reject', $rejected), ['reason' => 'nota tidak terbaca jelas'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('panel.pengeluaran.approve', $approved))->assertSessionHas('error');

        $entry = FinancialEntry::sole();
        $this->assertSame(['expense', null, -75000, $approved->expense_date->toDateString()], [$entry->entry_type, $entry->channel, $entry->amount, $entry->business_date->toDateString()]);
        $this->assertSame(Expense::STATUS_REJECTED, $rejected->fresh()->status);
        $this->artisan('ledger:verify')->assertSuccessful();
    }

    public function test_pengeluaran_di_periode_terkunci_ditolak(): void
    {
        ClosedPeriod::create(['period_start' => now('Asia/Jakarta')->subDays(10)->toDateString(), 'period_end' => now('Asia/Jakarta')->subDays(2)->toDateString()]);

        $this->submit(['expense_date' => now('Asia/Jakarta')->subDays(5)->toDateString()])->assertSessionHas('error', fn ($m) => str_contains($m, 'ditutup buku'));
        $this->assertSame(0, Expense::count());
    }

    public function test_nota_hanya_bisa_dibuka_pembuat_dan_admin(): void
    {
        $this->submit();
        $expense = Expense::sole();

        $this->actingAs($this->pegawai)->get(route('panel.pengeluaran.receipt', $expense))->assertOk();
        $this->actingAs($this->admin)->get(route('panel.pengeluaran.receipt', $expense))->assertOk();
        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->get(route('panel.pengeluaran.receipt', $expense))->assertNotFound();

        // Daftar: pegawai lain tidak melihat pengeluaran ini
        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->get(route('panel.pengeluaran.index'))->assertDontSee('data-expense="' . $expense->id . '"', false);
    }

    public function test_admin_mengelola_master_kategori(): void
    {
        $this->actingAs($this->pegawai)->post(route('panel.pengeluaran.category.store'), ['name' => 'Bensin'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('panel.pengeluaran.category.store'), ['name' => 'Bensin'])->assertSessionHas('success');

        $category = ExpenseCategory::where('name', 'Bensin')->sole();
        $this->actingAs($this->admin)->post(route('panel.pengeluaran.category.toggle', $category));
        $this->assertFalse($category->fresh()->is_active);

        $this->submit(['category_id' => $category->id])->assertSessionHasErrors('category_id');
    }
}
