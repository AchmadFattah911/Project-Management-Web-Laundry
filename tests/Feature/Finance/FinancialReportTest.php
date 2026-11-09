<?php

namespace Tests\Feature\Finance;

use App\Data\ReportFilter;
use App\Enums\UserRole;
use App\Models\FinancialEntry;
use App\Services\FinancialReportService;
use App\Services\LedgerService;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 7: laporan keuangan admin dari ledger.
 */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private int $seq = 0;

    /**
     * Entri ledger langsung (waktu UTC), tanggal bisnis dihitung dari zona outlet.
     */
    private function entry(string $type, ?string $channel, int $amount, string $utc, string $method = 'cash'): FinancialEntry
    {
        $at = Carbon::parse($utc, 'UTC');

        return app(LedgerService::class)->append([
            'entry_type' => $type, 'channel' => $channel, 'source_type' => 'uji', 'source_id' => ++$this->seq,
            'amount' => $amount, 'payment_method' => $type === 'expense' ? null : $method,
            'occurred_at' => $at, 'business_date' => BusinessCalendar::dateOf($at),
        ]);
    }

    private function report(array $input): array
    {
        return app(FinancialReportService::class)->build(ReportFilter::fromArray($input));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 05:00:00', 'UTC'));
    }

    public function test_jam_2330_dan_0030_wib_masuk_hari_yang_benar(): void
    {
        $this->entry('sale', 'outlet', 10000, '2026-11-10 16:30:00'); // 23.30 WIB 10 Nov
        $this->entry('sale', 'outlet', 20000, '2026-11-10 17:30:00'); // 00.30 WIB 11 Nov

        $trend = collect($this->report(['periode' => 'harian'])['trend'])->keyBy('key');

        $this->assertSame(10000, $trend['2026-11-10']['income']);
        $this->assertSame(20000, $trend['2026-11-11']['income']);
    }

    public function test_minggu_senin_sampai_minggu_dan_bucket_kosong_nol(): void
    {
        $this->entry('sale', 'online', 5000, '2026-11-15 10:00:00', 'qris');  // Minggu 15 Nov → minggu 9 Nov
        $this->entry('sale', 'online', 7000, '2026-11-16 02:00:00', 'qris');  // Senin 16 Nov → minggu 16 Nov

        $trend = collect($this->report(['periode' => 'mingguan', 'dari' => '2026-11-01', 'sampai' => '2026-11-22'])['trend']);

        $this->assertSame(['2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16'], $trend->pluck('key')->all());
        $this->assertSame([0, 0, 5000, 7000], $trend->pluck('income')->all());

        // Harian: semua hari di bulan berjalan tampil, yang kosong = 0
        $daily = collect($this->report(['periode' => 'harian'])['trend']);
        $this->assertCount(30, $daily);
        $this->assertSame(0, $daily->firstWhere('key', '2026-11-01')['income']);
    }

    public function test_kpi_dan_outlet_plus_online_sama_dengan_gabungan(): void
    {
        $this->entry('sale', 'outlet', 30000, '2026-11-05 03:00:00');
        $this->entry('sale', 'outlet', 15000, '2026-11-05 04:00:00', 'qris');
        $this->entry('void_reversal', 'outlet', -15000, '2026-11-06 03:00:00', 'qris');
        $this->entry('sale', 'online', 40000, '2026-11-07 03:00:00', 'gopay');
        $this->entry('gateway_fee', 'online', -800, '2026-11-07 03:00:00', 'gopay');
        $this->entry('refund', 'online', -40000, '2026-11-08 03:00:00', 'gopay');
        $this->entry('sale', 'online', 20000, '2026-11-09 03:00:00', 'qris');
        $this->entry('expense', null, -12000, '2026-11-09 05:00:00');

        $all = $this->report(['kanal' => 'gabungan'])['kpi'];
        $outlet = $this->report(['kanal' => 'outlet'])['kpi'];
        $online = $this->report(['kanal' => 'online'])['kpi'];

        $this->assertSame([
            'gross' => 105000, 'void' => -15000, 'refund' => -40000, 'gateway_fee' => -800, 'net' => 49200,
            'expense' => -12000, 'profit' => 37200, 'transactions' => 4, 'average' => 26250,
        ], $all);

        foreach (['gross', 'void', 'refund', 'gateway_fee', 'net', 'transactions'] as $key) {
            $this->assertSame($all[$key], $outlet[$key] + $online[$key], "Outlet + Online = Gabungan ({$key})");
        }
        $this->assertSame(0, $outlet['expense'], 'Pengeluaran hanya di Gabungan.');

        // Per metode
        $methods = collect($this->report([])['methods'])->keyBy('method');
        $this->assertSame(['gross' => 35000, 'corrections' => -15000, 'net' => 20000], collect($methods['qris'])->only(['gross', 'corrections', 'net'])->all());
    }

    public function test_ringkasan_sama_dengan_jumlah_detail(): void
    {
        $this->entry('sale', 'outlet', 30000, '2026-11-05 03:00:00');
        $this->entry('sale', 'online', 25000, '2026-11-12 03:00:00', 'qris');
        $this->entry('gateway_fee', 'online', -175, '2026-11-12 03:00:00', 'qris');
        $this->entry('expense', null, -9000, '2026-11-13 03:00:00');
        $this->entry('sale', 'online', 99999, '2026-10-31 03:00:00', 'qris'); // di luar rentang

        $service = app(FinancialReportService::class);
        $filter = ReportFilter::fromArray([]);
        $kpi = $service->kpi($filter);

        $this->assertSame($kpi['profit'], (int) $service->detailQuery($filter)->get()->sum('amount'));
        $this->assertSame(4, $service->countEntries($filter));
        $this->assertSame($kpi['net'], collect($service->trend($filter))->sum('income'));
    }

    public function test_hanya_admin_yang_bisa_membuka_laporan(): void
    {
        $this->entry('sale', 'outlet', 30000, '2026-11-05 03:00:00');

        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->get(route('panel.laporan.index'))->assertForbidden();
        $this->actingAs($this->makeUser())->get(route('panel.laporan.index'))->assertForbidden();

        $this->actingAs($this->makeUser(UserRole::ADMIN))->get(route('panel.laporan.index'))
            ->assertOk()->assertSee('Pendapatan Bruto')->assertSee('Rp 30.000')->assertSee('estimasi');
    }

    public function test_rentang_kustom_tidak_valid_ditolak(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->get(route('panel.laporan.index', ['dari' => '2026-11-10', 'sampai' => '2026-11-01']))
            ->assertRedirect(route('panel.laporan.index'))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('panel.laporan.index', ['periode' => 'harian', 'dari' => '2020-01-01', 'sampai' => '2026-11-01']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'terlalu panjang'));
    }
}
