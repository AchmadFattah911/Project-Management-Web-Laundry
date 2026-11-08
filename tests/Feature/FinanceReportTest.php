<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        Carbon::setTestNow(Carbon::parse('2026-11-16 05:00:00', 'UTC')); // Senin 12.00 WIB
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Pesanan tunggal dengan 1 pembayaran. Waktu dalam UTC seperti tersimpan di database.
     */
    private function pay(int $amount, string $paidAt, array $attributes = [], array $orderAttributes = []): Payment
    {
        $order = $this->makeOrder(null, $this->makeService(), $orderAttributes + ['total_price' => $amount, 'customer_name' => 'Budi']);

        return $order->payments()->create([
            'amount'  => $amount,
            'method'  => 'transfer',
            'gateway' => 'dummy',
            'status'  => Payment::STATUS_PAID,
            'paid_at' => Carbon::parse($paidAt, 'UTC'),
            ...$attributes,
        ]);
    }

    private function report(array $query = []): array
    {
        return $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index', $query))
            ->assertOk()
            ->viewData('report');
    }

    public function test_hanya_pegawai_yang_bisa_membuka_laporan(): void
    {
        $this->actingAs($this->makeUser())->get(route('pegawai.keuangan.index'))->assertForbidden();
        $this->actingAs($this->makeUser())->get(route('pegawai.keuangan.export'))->assertForbidden();
        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index'))->assertOk()->assertSee('Laporan Keuangan');
    }

    public function test_bawaan_harian_hari_ini_dan_hanya_menghitung_yang_lunas(): void
    {
        $this->pay(50000, '2026-11-16 03:00:00');
        $this->pay(70000, '2026-11-16 03:00:00', ['status' => Payment::STATUS_PENDING, 'paid_at' => null]);
        $this->pay(20000, '2026-11-16 03:00:00', ['status' => Payment::STATUS_FAILED, 'paid_at' => null]);

        $report = $this->report();

        $this->assertSame('harian', $report['period']);
        $this->assertSame('online', $report['channel']);
        $this->assertSame(50000.0, $report['total']);
        $this->assertSame(1, $report['count']);
    }

    public function test_batas_hari_memakai_waktu_outlet_bukan_utc(): void
    {
        $this->pay(10000, '2026-11-15 16:59:59'); // 15 Nov 23.59 WIB
        $this->pay(20000, '2026-11-15 17:00:00'); // 16 Nov 00.00 WIB
        $this->pay(40000, '2026-11-16 16:59:59'); // 16 Nov 23.59 WIB
        $this->pay(80000, '2026-11-16 17:00:00'); // 17 Nov 00.00 WIB

        $this->assertSame(60000.0, $this->report(['tanggal' => '2026-11-16'])['total']);
    }

    public function test_saluran_memisahkan_online_dan_kasir(): void
    {
        $this->pay(50000, '2026-11-16 03:00:00');
        $this->pay(30000, '2026-11-16 03:00:00', ['gateway' => Payment::GATEWAY_MANUAL, 'method' => 'transfer']);
        // Tagihan online yang dilunasi manual oleh admin bukan uang yang masuk lewat gateway
        $this->pay(10000, '2026-11-16 03:00:00', ['method' => 'cash', 'meta' => ['confirmed_by' => 1]]);

        $this->assertSame(50000.0, $this->report(['kanal' => 'online'])['total']);
        $this->assertSame(40000.0, $this->report(['kanal' => 'kasir'])['total']);
        $this->assertSame(90000.0, $this->report(['kanal' => 'semua'])['total']);
    }

    public function test_rekap_bulanan_dan_mingguan_memuat_semua_hari(): void
    {
        $this->pay(10000, '2026-11-02 03:00:00');
        $this->pay(20000, '2026-11-16 03:00:00');
        $this->pay(30000, '2026-11-16 04:00:00');
        $this->pay(90000, '2026-10-31 03:00:00');

        $month = $this->report(['periode' => 'bulanan', 'tanggal' => '2026-11-16']);
        $this->assertSame(60000.0, $month['total']);
        $this->assertCount(30, $month['byDay']);
        $this->assertSame('November 2026', $month['label']);
        $this->assertSame(50000.0, $month['byDay'][15]['total']);
        $this->assertSame(2, $month['byDay'][15]['count']);
        $this->assertSame(0.0, $month['byDay'][0]['total']);

        $week = $this->report(['periode' => 'mingguan', 'tanggal' => '2026-11-18']);
        $this->assertSame(50000.0, $week['total']);
        $this->assertCount(7, $week['byDay']);
        $this->assertSame('2026-11-16', $week['start']->format('Y-m-d'));
    }

    public function test_transaksi_keranjang_dihitung_sekali_dan_dipecah_per_layanan(): void
    {
        $customer = $this->makeUser();
        $checkout = Checkout::create(['code' => 'TRX-UJI', 'user_id' => $customer->id, 'total_amount' => 80000, 'status' => Checkout::STATUS_PAID, 'contact_phone' => '081234567890']);
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat']);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja']);
        $this->makeOrder($customer, $lipat, ['checkout_id' => $checkout->id, 'total_price' => 50000]);
        $this->makeOrder($customer, $saja, ['checkout_id' => $checkout->id, 'total_price' => 30000]);
        $checkout->payments()->create([
            'amount' => 80000, 'method' => 'e-wallet', 'gateway' => 'dummy',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::parse('2026-11-16 03:00:00', 'UTC'),
        ]);

        $report = $this->report();

        $this->assertSame(80000.0, $report['total']);
        $this->assertSame(1, $report['count']);
        $this->assertTrue($report['mismatches']->isEmpty());
        $this->assertSame(['Cuci Lipat', 'Cuci Saja'], $report['sources']->pluck('label')->all());
        $this->assertSame([50000.0, 30000.0], $report['sources']->pluck('total')->all());
        $this->assertSame(80000.0, $report['destinations']->firstWhere('label', 'E-Wallet / QRIS')['total']);
    }

    public function test_audit_menandai_selisih_dan_bayar_ganda_tanpa_menghitungnya_sebagai_pemasukan(): void
    {
        $this->pay(30000, '2026-11-16 03:00:00', orderAttributes: ['total_price' => 60000]);
        $this->pay(15000, '2026-11-16 03:00:00', [
            'status' => Payment::STATUS_EXPIRED, 'paid_at' => null, 'meta' => ['needs_refund' => true],
        ]);

        $report = $this->report();

        $this->assertSame(30000.0, $report['total']);
        $this->assertSame(-30000.0, $report['mismatches']->sole()['difference']);
        $this->assertSame(15000.0, $report['refundTotal']);

        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index'))
            ->assertSee('Nominal selisih')
            ->assertSee('Bayar ganda');
    }

    public function test_rincian_selalu_berjumlah_sama_dengan_total_pemasukan_walau_ada_selisih(): void
    {
        $customer = $this->makeUser();
        $checkout = Checkout::create(['code' => 'TRX-KURANG', 'user_id' => $customer->id, 'total_amount' => 100, 'status' => Checkout::STATUS_PAID, 'contact_phone' => '081234567890']);

        // Uang masuk 100 untuk 3 pesanan senilai sama: pembagiannya tidak habis dibagi
        foreach (['Cuci A', 'Cuci B', 'Cuci C'] as $name) {
            $this->makeOrder($customer, $this->makeService(attributes: ['name' => $name]), ['checkout_id' => $checkout->id, 'total_price' => 50]);
        }
        $checkout->payments()->create([
            'amount' => 100, 'method' => 'transfer', 'gateway' => 'dummy',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::parse('2026-11-16 03:00:00', 'UTC'),
        ]);
        $this->pay(30000, '2026-11-16 03:00:00', ['method' => 'e-wallet'], ['total_price' => 60000]);

        $report = $this->report();

        $this->assertSame(30100.0, $report['total']);
        $this->assertSame(30100.0, round($report['sources']->sum('total'), 2));
        $this->assertSame(30100.0, round($report['destinations']->sum('total'), 2));
    }

    public function test_tempat_uang_tanpa_pemasukan_tetap_tampil_dan_tunai_hanya_di_kasir(): void
    {
        $this->pay(50000, '2026-11-16 03:00:00');

        $online = $this->report()['destinations'];
        $this->assertEqualsCanonicalizing(['Rekening Bank', 'E-Wallet / QRIS'], $online->pluck('label')->all());
        $this->assertSame(0.0, $online->firstWhere('label', 'E-Wallet / QRIS')['total']);
        $this->assertSame(100, $online->firstWhere('label', 'Rekening Bank')['share']);

        $this->assertNotNull($this->report(['kanal' => 'semua'])['destinations']->firstWhere('label', 'Laci Kasir'));

        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index'))
            ->assertSee('Uang Masuk ke Mana?')
            ->assertSee('Uang dari Layanan Apa?')
            ->assertSee('Cocokkan dengan mutasi rekening');
    }

    public function test_export_csv_berisi_transaksi_dan_menetralkan_rumus(): void
    {
        $this->pay(50000, '2026-11-16 03:00:00', orderAttributes: ['customer_name' => '=HYPERLINK("x")']);

        $response = $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.export'))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('rekap-keuangan-online-harian-2026-11-16.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Total;;;;;;;;;;50000', $csv);
    }

    public function test_parameter_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index', ['periode' => 'tahunan', 'tanggal' => 'kemarin']))
            ->assertSessionHasErrors(['periode', 'tanggal']);
    }
}
