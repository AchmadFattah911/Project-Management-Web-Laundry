<?php

namespace Tests\Feature\Finance;

use App\Data\ReportFilter;
use App\Enums\UserRole;
use App\Exceptions\ExportIntegrityException;
use App\Jobs\GenerateFinanceExport;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\FinanceExport;
use App\Models\FinancialEntry;
use App\Models\User;
use App\Services\FinanceExcelExporter;
use App\Services\FinanceExportService;
use App\Services\FinancialReportService;
use App\Services\LedgerService;
use App\Support\BusinessCalendar;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 8: export Excel laporan keuangan (dibaca ulang dengan openspout).
 */
class FinanceExportTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $admin;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-20 05:00:00', 'UTC'));
        $this->admin = $this->makeUser(UserRole::ADMIN, ['name' => 'Admin Uji']);
    }

    private function entry(string $type, ?string $channel, int $amount, string $utc, string $method = 'cash', ?int $checkoutId = null): void
    {
        $at = Carbon::parse($utc, 'UTC');
        app(LedgerService::class)->append([
            'entry_type' => $type, 'channel' => $channel, 'source_type' => 'uji', 'source_id' => ++$this->seq,
            'amount' => $amount, 'payment_method' => $type === 'expense' ? null : $method,
            'occurred_at' => $at, 'business_date' => BusinessCalendar::dateOf($at), 'checkout_id' => $checkoutId,
        ]);
    }

    private function seedLedger(): void
    {
        // Pelanggan dengan nama berbahaya (formula injection)
        $evil = $this->makeUser(attributes: ['name' => '=HYPERLINK("http://x","klik")']);
        $checkout = Checkout::create(['code' => 'OTL-EVIL0001', 'channel' => 'outlet', 'user_id' => $evil->id, 'total_amount' => 30000, 'status' => 'lunas', 'contact_phone' => '0812', 'submission_token' => (string) Str::uuid()]);

        $this->entry('sale', 'outlet', 30000, '2026-11-05 03:00:00', 'cash', $checkout->id);
        $this->entry('void_reversal', 'outlet', -30000, '2026-11-06 03:00:00', 'cash', $checkout->id);
        $this->entry('sale', 'online', 40000, '2026-11-07 03:00:00', 'qris');
        $this->entry('gateway_fee', 'online', -280, '2026-11-07 03:00:00', 'qris');
        $this->entry('refund', 'online', -10000, '2026-11-08 03:00:00', 'qris');
        $this->entry('expense', null, -12000, '2026-11-09 03:00:00');
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $sheets;
    }

    public function test_export_berisi_semua_sheet_angka_numerik_dan_total_cocok_dengan_laporan(): void
    {
        $this->seedLedger();

        $response = $this->actingAs($this->admin)->post(route('panel.ekspor.store'), ['kanal' => 'gabungan', 'periode' => 'harian']);
        $export = FinanceExport::sole();
        $response->assertRedirect(route('panel.ekspor.download', $export));
        $this->assertSame(FinanceExport::STATUS_DONE, $export->status);

        $this->actingAs($this->admin)->get(route('panel.ekspor.download', $export))->assertOk()->assertDownload($export->fileName());

        $book = $this->readWorkbook(Storage::disk('local')->path($export->path));
        $this->assertSame(['Info', 'Ringkasan', 'Outlet', 'Online', 'Refund & Void', 'Pengeluaran', 'Kontrol'], array_keys($book));

        // Info: tanggal bertipe DATE, pembuat & zona waktu
        $info = collect($book['Info'])->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
        $this->assertInstanceOf(DateTimeInterface::class, $info['Periode dari']);
        $this->assertSame('2026-11-01', $info['Periode dari']->format('Y-m-d'));
        $this->assertStringContainsString('Admin Uji', $info['Dibuat oleh']);
        $this->assertSame('Asia/Jakarta', $info['Zona waktu']);

        // Ringkasan = laporan di layar
        $kpi = app(FinancialReportService::class)->kpi(ReportFilter::fromArray(['periode' => 'harian']));
        $summary = collect($book['Ringkasan'])->mapWithKeys(fn ($r) => [$r[0] => $r[1] ?? null]);
        $this->assertSame($kpi['gross'], $summary['Pendapatan bruto']);
        $this->assertSame($kpi['net'], $summary['Pendapatan neto']);
        $this->assertSame($kpi['profit'], $summary['Laba']);
        $this->assertIsInt($summary['Laba']);

        // Detail: header tetap, angka numerik, tanggal DATE
        $this->assertSame(FinanceExcelExporter::DETAIL_HEADERS, $book['Outlet'][0]);
        $outletRow = $book['Outlet'][1];
        $this->assertInstanceOf(DateTimeInterface::class, $outletRow[0]);
        $this->assertSame('2026-11-05', $outletRow[0]->format('Y-m-d'));
        $this->assertSame('2026-11-05 10:00', $outletRow[1]->format('Y-m-d H:i'), 'Waktu dalam WIB.');
        $this->assertSame([30000, 0, 30000], [$outletRow[8], $outletRow[9], $outletRow[10]]);
        $this->assertCount(4, $book['Online'], 'Header + sale + biaya + refund.');
        $feeRow = collect($book['Online'])->first(fn ($r) => $r[7] === FinancialEntry::TYPE_LABELS['gateway_fee']);
        $this->assertSame([0, -280, -280], [$feeRow[8], $feeRow[9], $feeRow[10]]);
        $this->assertCount(3, $book['Refund & Void']);
        $this->assertSame(-12000, $book['Pengeluaran'][1][10]);

        // Formula injection ter-escape (string, bukan formula)
        $this->assertSame("'=HYPERLINK(\"http://x\",\"klik\")", $outletRow[4]);

        // Kontrol: selisih 0
        $control = collect($book['Kontrol']);
        $this->assertSame(['Selisih', 0], [$control->last()[0], $control->last()[3]]);
        $control->slice(1, -1)->each(fn ($row) => $this->assertSame(0, $row[3], $row[0]));

        $this->assertTrue(AuditLog::where('action', 'laporan.export')->where('actor_id', $this->admin->id)->exists());
    }

    public function test_kontrol_tidak_nol_menggagalkan_export_tanpa_file(): void
    {
        $this->seedLedger();
        $reports = \Mockery::mock(FinancialReportService::class)->makePartial();
        $reports->shouldReceive('kpi')->andReturnUsing(fn ($f) => ['gross' => 1, 'void' => 0, 'refund' => 0, 'gateway_fee' => 0, 'net' => 1, 'expense' => 0, 'profit' => 1, 'transactions' => 1, 'average' => 1]);
        $this->app->instance(FinancialReportService::class, $reports);

        $path = Storage::disk('local')->path('kontrol.xlsx');

        try {
            app(FinanceExcelExporter::class)->write(ReportFilter::fromArray([]), $this->admin, $path);
            $this->fail('Export dengan selisih harus gagal.');
        } catch (ExportIntegrityException $e) {
            $this->assertStringContainsString('selisih', $e->getMessage());
        }

        $this->assertFileDoesNotExist($path);

        // Lewat service: tercatat failed + audit
        $result = app(FinanceExportService::class)->request(ReportFilter::fromArray([]), $this->admin);
        $this->assertSame(FinanceExport::STATUS_FAILED, $result['export']->status);
        $this->assertNull($result['export']->path);
        $this->assertTrue(AuditLog::where('action', 'laporan.export.failed')->exists());
    }

    public function test_rentang_besar_lewat_queue_dan_diunduh_dari_ekspor_saya(): void
    {
        $this->seedLedger();
        config(['finance.export.sync_row_limit' => 2]);
        Queue::fake();

        $this->actingAs($this->admin)->post(route('panel.ekspor.store'), ['periode' => 'harian'])
            ->assertRedirect(route('panel.ekspor.index'))->assertSessionHas('status');

        $export = FinanceExport::sole();
        $this->assertSame(FinanceExport::STATUS_QUEUED, $export->status);
        Queue::assertPushed(GenerateFinanceExport::class, fn ($job) => $job->exportId === $export->id);

        (new GenerateFinanceExport($export->id))->handle(app(FinanceExportService::class));
        $this->assertSame(FinanceExport::STATUS_DONE, $export->fresh()->status);

        $this->actingAs($this->admin)->get(route('panel.ekspor.index'))->assertSee('Siap diunduh');
        $this->actingAs($this->makeUser(UserRole::ADMIN))->get(route('panel.ekspor.download', $export))->assertNotFound();
    }

    public function test_pegawai_tidak_bisa_export(): void
    {
        $pegawai = $this->makeUser(UserRole::PEGAWAI);

        $this->actingAs($pegawai)->post(route('panel.ekspor.store'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('panel.ekspor.index'))->assertForbidden();
        $this->assertSame(0, FinanceExport::count());
    }

    public function test_escape_formula_injection(): void
    {
        foreach (['=1+1', '+62812', '-5', '@SUM(A1)', "\tx"] as $value) {
            $this->assertStringStartsWith("'", FinanceExcelExporter::escape($value));
        }
        $this->assertSame('Budi', FinanceExcelExporter::escape('Budi'));
    }
}
