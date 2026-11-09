<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashShift;
use App\Models\User;
use App\Models\VoidRequest;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\CreatesFinanceData;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 4: kas harian / shift.
 */
class CashShiftTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, CreatesFinanceData;

    private User $pegawai;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
    }

    public function test_buka_shift_dan_tidak_bisa_buka_dua_shift(): void
    {
        $this->actingAs($this->pegawai)->post(route('panel.shift.open'), ['opening_balance' => 200000, 'submission_token' => (string) Str::uuid()])
            ->assertSessionHas('success');

        $this->actingAs($this->pegawai)->post(route('panel.shift.open'), ['opening_balance' => 50000, 'submission_token' => (string) Str::uuid()])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'belum ditutup'));

        $this->assertSame(1, CashShift::count());
        $this->assertSame(200000, CashShift::sole()->opening_balance);
        $this->assertTrue(AuditLog::where('action', 'shift.open')->exists());

        // Petugas lain tetap boleh punya shift sendiri
        $this->openShift($this->makeUser(UserRole::PEGAWAI));
        $this->assertSame(2, CashShift::count());
    }

    public function test_submit_ganda_buka_shift_satu_data(): void
    {
        $token = (string) Str::uuid();
        foreach ([1, 2] as $i) {
            $this->actingAs($this->pegawai)->post(route('panel.shift.open'), ['opening_balance' => 100000, 'submission_token' => $token]);
        }

        $this->assertSame(1, CashShift::count());
    }

    public function test_tutup_shift_menghitung_selisih_dari_tunai_masuk_dan_void(): void
    {
        $shift = $this->openShift($this->pegawai, 100000);
        $customer = $this->makeUser();
        $service = $this->makeService(attributes: ['price' => 15000]);

        // 2 transaksi tunai (30.000 + 15.000), 1 QRIS (tidak masuk laci), lalu 1 tunai di-void (-15.000)
        $this->createOutlet($this->pegawai, $this->outletPayload($customer, $service));
        $voided = $this->createOutlet($this->pegawai, $this->outletPayload($customer, $service, ['items' => [['service_id' => $service->id, 'qty' => 1]], 'cash_received' => 20000]));
        $this->createOutlet($this->pegawai, $this->outletPayload($customer, $service, ['payment_method' => 'qris', 'cash_received' => null, 'payment_reference' => 'QR-1']));
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $voided), ['reason' => 'salah input layanan pelanggan', 'submission_token' => (string) Str::uuid()]);
        $this->actingAs($this->admin)->post(route('panel.void.approve', VoidRequest::sole()));

        $summary = app(ShiftService::class)->summary($shift);
        $this->assertSame(['opening' => 100000, 'cash_in' => 45000, 'cash_out' => 15000, 'expected' => 130000, 'non_cash' => 30000, 'transactions' => 3], $summary);

        // Uang fisik: 1×100.000 + 2×10.000 + 1×5.000 = 125.000 → kurang 5.000
        $this->actingAs($this->pegawai)->post(route('panel.shift.close', $shift), [
            'mode' => 'pecahan', 'denominations' => ['100000' => 1, '10000' => 2, '5000' => 1, '2000' => ''], 'submission_token' => (string) Str::uuid(),
        ])->assertRedirect(route('panel.shift.show', $shift));

        $shift->refresh();
        $this->assertSame([CashShift::STATUS_CLOSED, 130000, 125000, -5000], [$shift->status, $shift->expected_cash, $shift->counted_cash, $shift->difference]);
        $this->assertEquals([100000 => 1, 10000 => 2, 5000 => 1], $shift->denominations);
    }

    public function test_tutup_shift_dengan_total_dan_verifikasi_admin(): void
    {
        $shift = $this->openShift($this->pegawai, 50000);

        $this->actingAs($this->pegawai)->post(route('panel.shift.close', $shift), ['mode' => 'total', 'counted_total' => 50000, 'submission_token' => (string) Str::uuid()])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'sesuai'));
        $this->assertSame(0, $shift->fresh()->difference);

        $this->actingAs($this->pegawai)->post(route('panel.shift.verify', $shift))->assertForbidden();
        $this->actingAs($this->admin)->post(route('panel.shift.verify', $shift), ['note' => 'cocok'])->assertSessionHas('success');
        $this->assertSame(CashShift::STATUS_VERIFIED, $shift->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'shift.verify')->exists());
    }

    public function test_shift_hanya_ditutup_pemiliknya_dan_tidak_bisa_tutup_dua_kali(): void
    {
        $shift = $this->openShift($this->pegawai);
        $other = $this->makeUser(UserRole::PEGAWAI);

        $this->actingAs($other)->post(route('panel.shift.close', $shift), ['mode' => 'total', 'counted_total' => 1, 'submission_token' => (string) Str::uuid()])->assertNotFound();

        $service = app(ShiftService::class);
        $service->close($shift, $this->pegawai, null, 100000, null, (string) Str::uuid());

        $this->expectException(InvalidArgumentException::class);
        $service->close($shift, $this->pegawai, null, 100000, null, (string) Str::uuid());
    }

    public function test_pegawai_hanya_melihat_shift_miliknya_dan_laporan_lama_dialihkan(): void
    {
        $mine = $this->openShift($this->pegawai);
        $otherShift = $this->openShift($this->makeUser(UserRole::PEGAWAI));

        $html = $this->actingAs($this->pegawai)->get(route('panel.shift.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-shift="' . $mine->id . '"', $html);
        $this->assertStringNotContainsString('data-shift="' . $otherShift->id . '"', $html);
        $this->actingAs($this->pegawai)->get(route('panel.shift.show', $otherShift))->assertNotFound();

        $this->actingAs($this->admin)->get(route('panel.shift.index'))->assertSee('data-shift="' . $otherShift->id . '"', false);

        // URL laporan keuangan pegawai lama: tetap jalan, diarahkan ke ringkasan shift; export ditolak
        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index'))->assertRedirect(route('panel.shift.index'));
        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.export'))->assertForbidden();
    }
}
