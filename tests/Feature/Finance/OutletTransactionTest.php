<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\ClosedPeriod;
use App\Models\FinancialEntry;
use App\Models\Order;
use App\Models\User;
use App\Models\VoidRequest;
use App\Services\PaymentReconciliationService;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFinanceData;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 3: halaman transaksi dengan tab Outlet/Aplikasi, transaksi outlet, pelunasan, edit, void + approval.
 */
class OutletTransactionTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, CreatesFinanceData;

    private User $pegawai;

    private User $admin;

    private User $customer;

    private \App\Models\Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
        $this->customer = $this->makeUser(attributes: ['name' => 'Bu Tini', 'phone' => '081234567890']);
        $this->service = $this->makeService(attributes: ['price' => 15000]);
    }

    public function test_transaksi_tunai_bayar_sekarang_terikat_shift_dan_saluran_dari_sistem(): void
    {
        $shift = $this->openShift($this->pegawai);
        $payload = $this->outletPayload($this->customer, $this->service, ['channel' => 'online', 'total_amount' => 1, 'unit_price' => 1]);

        $this->actingAs($this->pegawai)->post(route('panel.transaksi.store'), $payload)
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Kembalian Rp 20.000'));

        $checkout = Checkout::sole();
        $this->assertSame(['outlet', 'lunas', '30000.00', $shift->id, $this->pegawai->id], [$checkout->channel, $checkout->status, $checkout->total_amount, $checkout->shift_id, $checkout->created_by]);
        $this->assertSame(['outlet'], $checkout->orders()->pluck('channel')->unique()->all());

        $sale = FinancialEntry::sole();
        $this->assertSame(['sale', 'outlet', 30000, 'cash', $shift->id], [$sale->entry_type, $sale->channel, $sale->amount, $sale->payment_method, $sale->shift_id]);
        $this->assertTrue(AuditLog::where('action', 'transaksi.outlet.create')->exists());
    }

    public function test_tunai_tanpa_shift_ditolak(): void
    {
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.store'), $this->outletPayload($this->customer, $this->service))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'shift'));

        $this->assertSame(0, Checkout::count());
        $this->assertSame(0, FinancialEntry::count());
    }

    public function test_qris_tanpa_shift_boleh_dan_wajib_nomor_bukti(): void
    {
        $base = ['payment_method' => 'qris', 'cash_received' => null];
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.store'), $this->outletPayload($this->customer, $this->service, $base))
            ->assertSessionHasErrors('payment_reference');

        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service, $base + ['payment_reference' => 'qr-123']));
        $this->assertTrue($checkout->isPaid());
        $this->assertSame('qris', FinancialEntry::sole()->payment_method);
    }

    public function test_submit_ganda_menghasilkan_satu_transaksi(): void
    {
        $this->openShift($this->pegawai);
        $payload = $this->outletPayload($this->customer, $this->service);

        $this->actingAs($this->pegawai)->post(route('panel.transaksi.store'), $payload);
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.store'), $payload)->assertSessionHas('success', fn ($m) => str_contains($m, 'tidak dibuat ulang'));

        $this->assertSame(1, Checkout::count());
        $this->assertSame(1, FinancialEntry::count());
    }

    public function test_tambah_cepat_pelanggan_baru_di_form_transaksi(): void
    {
        $payload = $this->outletPayload($this->customer, $this->service, ['customer_id' => null, 'new_customer_name' => 'Pak Joko', 'new_customer_phone' => '0813-1111-2222', 'payment_timing' => 'on_pickup']);
        $checkout = $this->createOutlet($this->pegawai, $payload);

        $this->assertSame('Pak Joko', $checkout->user->name);
        $this->assertSame('+6281311112222', $checkout->user->phone_e164);
        $this->assertTrue($checkout->user->isWalkIn());
    }

    public function test_bayar_saat_ambil_dikerjakan_dulu_lalu_ledger_dicatat_saat_lunas(): void
    {
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service, ['payment_timing' => 'on_pickup']));
        $order = $checkout->orders()->first();

        $this->assertSame(Checkout::STATUS_AWAITING, $checkout->status);
        $this->assertSame(0, FinancialEntry::count());

        $status = fn (string $to) => $this->actingAs($this->pegawai)->put(route('panel.transaksi.order-status', $order), ['status' => $to]);
        $status('diproses')->assertSessionHas('success');
        $status('siap_diambil')->assertSessionHas('success');
        $status('selesai')->assertSessionHas('error', fn ($m) => str_contains($m, 'belum dilunasi'));

        $this->openShift($this->pegawai);
        $token = (string) Str::uuid();
        $settle = fn () => $this->actingAs($this->pegawai)->post(route('panel.transaksi.settle', $checkout), ['payment_method' => 'cash', 'cash_received' => 30000, 'submission_token' => $token]);
        $settle()->assertSessionHas('success');
        $settle();

        $this->assertTrue($checkout->fresh()->isPaid());
        $this->assertSame(1, FinancialEntry::where('entry_type', 'sale')->count(), 'Pelunasan ganda tetap 1 entri.');
        $status('selesai')->assertSessionHas('success');
    }

    public function test_edit_hanya_sebelum_lunas(): void
    {
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service, ['payment_timing' => 'on_pickup']));
        $order = $checkout->orders()->first();

        $this->actingAs($this->pegawai)->put(route('panel.transaksi.update', $checkout), ['qty' => [$order->id => 3], 'notes' => 'tambah 1 mesin'])
            ->assertSessionHas('success');
        $this->assertSame('45000.00', $checkout->fresh()->total_amount);
        $this->assertTrue(AuditLog::where('action', 'transaksi.outlet.update')->exists());

        $this->openShift($this->pegawai);
        $paid = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service));
        $this->actingAs($this->pegawai)->put(route('panel.transaksi.update', $paid), ['qty' => [$paid->orders()->first()->id => 5]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah lunas'));
        $this->assertSame('30000.00', $paid->fresh()->total_amount);
    }

    public function test_void_butuh_persetujuan_admin_dan_membuat_entri_pembalik(): void
    {
        $this->openShift($this->pegawai);
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service));
        $token = (string) Str::uuid();

        $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $checkout), ['reason' => 'salah', 'submission_token' => $token])->assertSessionHasErrors('reason');
        foreach ([1, 2] as $i) {
            $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $checkout), ['reason' => 'pelanggan batal, salah input layanan', 'submission_token' => $token]);
        }
        $void = VoidRequest::sole();
        $this->assertSame(VoidRequest::STATUS_PENDING, $void->status);
        $this->assertSame(1, FinancialEntry::count(), 'Belum disetujui → belum ada pembalik.');

        $this->actingAs($this->pegawai)->post(route('panel.void.approve', $void))->assertForbidden();

        $this->actingAs($this->admin)->post(route('panel.void.approve', $void))->assertSessionHas('success');

        $reversal = FinancialEntry::where('entry_type', 'void_reversal')->sole();
        $this->assertSame([-30000, 'outlet', 'cash'], [$reversal->amount, $reversal->channel, $reversal->payment_method]);
        $this->assertSame(0, (int) FinancialEntry::sum('amount'));
        $this->assertSame(Checkout::STATUS_PAID, $checkout->fresh()->status, 'Transaksi asli tidak diubah.');
        $this->assertTrue($checkout->fresh()->isVoided());
        $this->assertTrue(AuditLog::where('action', 'transaksi.void.approve')->exists());

        // Pesanan transaksi void tidak bisa dikerjakan lagi
        $this->actingAs($this->pegawai)->put(route('panel.transaksi.order-status', $checkout->orders()->first()), ['status' => 'diproses'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'di-void'));
    }

    public function test_tolak_void_tanpa_entri_ledger(): void
    {
        $this->openShift($this->pegawai);
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service));
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $checkout), ['reason' => 'pelanggan batal, salah input', 'submission_token' => (string) Str::uuid()]);

        $this->actingAs($this->admin)->post(route('panel.void.reject', VoidRequest::sole()), ['reason' => 'cucian sudah diproses'])->assertSessionHas('success');

        $this->assertSame(0, FinancialEntry::where('entry_type', 'void_reversal')->count());
        $this->assertFalse($checkout->fresh()->isVoided());
    }

    public function test_void_hanya_untuk_transaksi_outlet(): void
    {
        $online = $this->onlineCheckout($this->customer, $this->service);

        $this->actingAs($this->admin)->post(route('panel.transaksi.void', $online), ['reason' => 'coba void transaksi online', 'submission_token' => (string) Str::uuid()])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'hanya untuk transaksi outlet'));
        $this->assertSame(0, VoidRequest::count());
    }

    public function test_void_di_periode_terkunci_ditolak(): void
    {
        $this->openShift($this->pegawai);
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service));
        $this->actingAs($this->pegawai)->post(route('panel.transaksi.void', $checkout), ['reason' => 'pelanggan batal, salah input', 'submission_token' => (string) Str::uuid()]);
        ClosedPeriod::create(['period_start' => BusinessCalendar::today(), 'period_end' => BusinessCalendar::today()]);

        $this->actingAs($this->admin)->post(route('panel.void.approve', VoidRequest::sole()))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ditutup buku'));

        $this->assertSame(VoidRequest::STATUS_PENDING, VoidRequest::sole()->status);
        $this->assertSame(0, FinancialEntry::where('entry_type', 'void_reversal')->count());
    }

    public function test_tab_aplikasi_hanya_lunas_untuk_pegawai(): void
    {
        $paid = $this->onlineCheckout($this->customer, $this->service);
        $awaiting = $this->onlineCheckout($this->customer, $this->service, Checkout::STATUS_AWAITING);
        $expired = $this->onlineCheckout($this->customer, $this->service, Checkout::STATUS_EXPIRED);

        $html = $this->actingAs($this->pegawai)->get(route('panel.transaksi.index', ['tab' => 'aplikasi']))->assertOk()->getContent();
        $this->assertStringContainsString($paid->code, $html);
        $this->assertStringNotContainsString($awaiting->code, $html);
        $this->assertStringNotContainsString($expired->code, $html);

        $this->actingAs($this->pegawai)->get(route('panel.transaksi.show', $awaiting))->assertNotFound();
        $this->actingAs($this->pegawai)->get(route('panel.transaksi.index', ['tab' => 'aplikasi', 'audit' => 1]))->assertForbidden();

        // Admin: audit pembayaran read-only menampilkan yang non-lunas
        $audit = $this->actingAs($this->admin)->get(route('panel.transaksi.index', ['tab' => 'aplikasi', 'audit' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString($awaiting->code, $audit);
        $this->assertStringNotContainsString($paid->code, $audit);
        $this->actingAs($this->admin)->get(route('panel.transaksi.show', $awaiting))->assertOk()->assertDontSee('Ajukan Void');
    }

    public function test_pegawai_memajukan_status_pesanan_aplikasi_tanpa_edit(): void
    {
        $paid = $this->onlineCheckout($this->customer, $this->service);
        $order = $paid->orders()->first();

        $this->actingAs($this->pegawai)->get(route('panel.transaksi.show', $paid))->assertOk()
            ->assertDontSee('Ubah Transaksi')->assertDontSee('Ajukan Void');
        $this->actingAs($this->pegawai)->put(route('panel.transaksi.order-status', $order), ['status' => 'diproses'])->assertSessionHas('success');
        $this->assertSame(Order::STATUS_DIPROSES, $order->fresh()->status);

        $this->actingAs($this->pegawai)->put(route('panel.transaksi.update', $paid), ['qty' => [$order->id => 9]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak bisa diubah dari kasir'));
    }

    public function test_filter_dan_pencarian_tab_outlet(): void
    {
        $this->openShift($this->pegawai);
        $cash = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service));
        $other = $this->makeUser(attributes: ['name' => 'Pak Budi', 'phone' => '081399990000']);
        $pickup = $this->createOutlet($this->pegawai, $this->outletPayload($other, $this->service, ['payment_timing' => 'on_pickup']));

        $get = fn (array $q) => $this->actingAs($this->pegawai)->get(route('panel.transaksi.index', $q))->getContent();

        $this->assertStringContainsString($pickup->code, $get(['search' => '081399990000']));
        $this->assertStringNotContainsString($cash->code, $get(['search' => '081399990000']));
        $this->assertStringContainsString($cash->code, $get(['status' => 'lunas']));
        $this->assertStringNotContainsString($pickup->code, $get(['status' => 'lunas']));
        $this->assertStringNotContainsString($cash->code, $get(['from' => now()->addDays(2)->toDateString()]));
    }

    public function test_struk_dan_transaksi_outlet_tidak_bisa_dibayar_online_oleh_pelanggan(): void
    {
        $checkout = $this->createOutlet($this->pegawai, $this->outletPayload($this->customer, $this->service, ['payment_timing' => 'on_pickup']));

        $this->actingAs($this->pegawai)->get(route('panel.transaksi.receipt', $checkout))->assertOk()->assertSee($checkout->code)->assertSee('Rp 30.000');

        $this->actingAs($this->customer)->get(route('pelanggan.pembayaran.show', $checkout))->assertNotFound();
        $this->actingAs($this->customer)->post(route('pelanggan.pembayaran.pay', $checkout))->assertNotFound();
        $this->actingAs($this->customer)->get(route('pelanggan.transaksi.show', $checkout))->assertOk()->assertSee($checkout->code);

        // Job kedaluwarsa tidak menyentuh transaksi outlet "bayar saat ambil"
        $this->travel(3)->hours();
        app(PaymentReconciliationService::class)->expireOverdue();
        $this->assertSame(Checkout::STATUS_AWAITING, $checkout->fresh()->status);
    }

    public function test_pelanggan_tidak_bisa_membuka_halaman_transaksi_panel(): void
    {
        $this->actingAs($this->customer)->get(route('panel.transaksi.index'))->assertForbidden();
    }
}
