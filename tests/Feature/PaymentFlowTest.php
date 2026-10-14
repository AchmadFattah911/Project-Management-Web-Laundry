<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Plan D: pembayaran online cashless lewat payment gateway (dummy) + konfirmasi manual kasir.
 */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelanggan = $this->makeUser();
    }

    /**
     * Order tunggal lama (sebelum ada keranjang) milik pelanggan, lalu pelanggan membuat tagihannya
     * lewat jalur pelanggan.orders.pay. Pesanan baru dibuat lewat keranjang (lihat CheckoutFlowTest).
     */
    private function placeOrder(string $method = 'transfer', int $qty = 2): array
    {
        $service = $this->makeService(attributes: ['price' => 15000]);
        $order = $this->makeOrder($this->pelanggan, $service, [
            'machine_qty' => $qty,
            'total_price' => 15000 * $qty,
        ]);

        $response = $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), [
            'payment_method' => $method,
        ]);

        return [$order, $order->payments()->latest('id')->first(), $response];
    }

    private function sendWebhook(Payment $payment, string $status, ?string $signature = null, ?string $body = null): TestResponse
    {
        $notification = app(DummyGateway::class)->buildNotification($payment, $status);
        $body ??= $notification['body'];
        $signature ??= $notification['headers'][DummyGateway::SIGNATURE_HEADER];

        return $this->call('POST', route('payments.webhook', 'dummy'), [], [], [], [
            'CONTENT_TYPE'           => 'application/json',
            'HTTP_X_DUMMY_SIGNATURE' => $signature,
        ], $body);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, config('payment.gateways.dummy.secret'));
    }

    // ── Membuat tagihan ─────────────────────────────────────────────

    public function test_pesanan_online_membuat_tagihan_dan_mengarahkan_ke_checkout(): void
    {
        [$order, $payment, $response] = $this->placeOrder('transfer', 2);

        $response->assertRedirect($payment->checkout_url);
        $this->assertStringContainsString('/pembayaran/dummy/DUMMY-', $payment->checkout_url);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('dummy', $payment->gateway);
        $this->assertSame('transfer', $payment->method);
        $this->assertEquals(30000, (float) $payment->amount);
        $this->assertEquals((float) $order->total_price, (float) $payment->amount);
        $this->assertTrue($payment->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));
    }

    public function test_pelanggan_wajib_memilih_metode_cashless(): void
    {
        $order = $this->makeOrder($this->pelanggan, $this->makeService());

        foreach ([null, 'cash', 'bitcoin'] as $method) {
            $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), array_filter([
                'payment_method' => $method,
            ]))->assertSessionHasErrors('payment_method');
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_route_pesan_lama_sudah_tidak_ada(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('pelanggan.orders.store'));
        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index'))
            ->assertOk()
            ->assertSee(route('pelanggan.pesan.index'), false)
            ->assertDontSee('orderModalOpen', false);
    }

    // ── Halaman checkout dummy ──────────────────────────────────────

    public function test_halaman_checkout_dummy_hanya_untuk_pemilik(): void
    {
        [, $payment] = $this->placeOrder();

        $this->actingAs($this->pelanggan)->get($payment->checkout_url)
            ->assertOk()
            ->assertSee('Mode Testing')
            ->assertSee($payment->reference);

        $lain = $this->makeUser();
        $this->actingAs($lain)->get($payment->checkout_url)->assertForbidden();
        $this->actingAs($lain)->post(route('payments.dummy.simulate', $payment->reference), ['result' => 'paid'])->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->actingAs($this->pelanggan)->get(route('payments.dummy.show', 'DUMMY-TIDAKADA'))->assertNotFound();
    }

    public function test_simulasi_bayar_berhasil_melunasi_pesanan(): void
    {
        [$order, $payment] = $this->placeOrder();

        $this->actingAs($this->pelanggan)
            ->post(route('payments.dummy.simulate', $payment->reference), ['result' => 'paid'])
            ->assertRedirect(route('pelanggan.orders.show', $order))
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.show', $order))
            ->assertSee('Pembayaran Lunas')
            ->assertDontSee('Bayar Sekarang');
    }

    public function test_dummy_gateway_mati_di_production(): void
    {
        [, $payment] = $this->placeOrder();

        $this->app['env'] = 'production';
        $this->assertFalse(PaymentService::dummyEnabled());
        $this->actingAs($this->pelanggan)->get($payment->checkout_url)->assertNotFound();
    }

    public function test_gateway_gagal_order_tetap_tersimpan_dan_bisa_dibayar_ulang(): void
    {
        config(['payment.gateways.dummy.enabled' => false]);

        [$order, $payment, $response] = $this->placeOrder();

        $this->assertNull($payment);
        $response->assertSessionHas('error');

        config(['payment.gateways.dummy.enabled' => true]);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'e-wallet'])
            ->assertRedirect($order->payments()->first()->checkout_url);
    }

    // ── Webhook ─────────────────────────────────────────────────────

    public function test_webhook_menolak_signature_salah_atau_kosong(): void
    {
        [, $payment] = $this->placeOrder();

        $this->sendWebhook($payment, 'paid', signature: 'salah')->assertStatus(400);
        $this->sendWebhook($payment, 'paid', signature: '')->assertStatus(400);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_webhook_menolak_nominal_yang_tidak_sesuai_tagihan(): void
    {
        [, $payment] = $this->placeOrder();

        $body = json_encode(['reference' => $payment->reference, 'status' => 'paid', 'amount' => '1000']);
        $this->sendWebhook($payment, 'paid', $this->sign($body), $body)->assertStatus(400);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_webhook_menolak_reference_tidak_dikenal_dan_gateway_tidak_terdaftar(): void
    {
        [, $payment] = $this->placeOrder();

        $body = json_encode(['reference' => 'DUMMY-TIDAKADA', 'status' => 'paid', 'amount' => '1']);
        $this->sendWebhook($payment, 'paid', $this->sign($body), $body)->assertStatus(400);

        $this->call('POST', route('payments.webhook', 'midtrans'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')
            ->assertNotFound();
    }

    public function test_webhook_valid_melunasi_dan_aman_dikirim_ulang(): void
    {
        [$order, $payment] = $this->placeOrder();

        $this->sendWebhook($payment, 'paid')->assertOk()->assertJson(['status' => 'paid']);
        $this->sendWebhook($payment, 'paid')->assertOk();
        $this->sendWebhook($payment->fresh(), 'failed')->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, $order->payments()->where('status', 'paid')->count());
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_webhook_tidak_butuh_csrf_tetapi_form_lain_tetap_dilindungi(): void
    {
        // Mode testing mematikan cek CSRF, jadi yang diuji adalah daftar pengecualiannya:
        // hanya webhook yang dikecualikan, semua form lain tetap wajib token CSRF.
        $except = app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertSame(['payments/webhook/*'], $except);
    }

    // ── Gagal / kadaluarsa / bayar ulang ────────────────────────────

    public function test_bayar_gagal_lalu_buat_tagihan_baru(): void
    {
        [$order, $first] = $this->placeOrder();

        $this->actingAs($this->pelanggan)->post(route('payments.dummy.simulate', $first->reference), ['result' => 'failed']);
        $this->assertSame('failed', $first->fresh()->status);

        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.show', $order))
            ->assertSee('Pembayaran Gagal')
            ->assertSee('Buat Tagihan Baru');

        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'e-wallet']);

        $second = $order->payments()->latest('id')->first();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('pending', $second->status);
        $this->assertSame('failed', $first->fresh()->status, 'Riwayat percobaan lama tidak boleh hilang.');
        $this->assertSame($second->id, $order->fresh()->load('payments')->paymentForDisplay()->id);
    }

    public function test_hanya_ada_satu_tagihan_aktif_dan_dana_di_tagihan_lama_tidak_hilang(): void
    {
        [$order, $first] = $this->placeOrder();

        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer']);
        $second = $order->payments()->latest('id')->first();

        $this->assertSame('expired', $first->fresh()->status);
        $this->assertSame(1, $order->payments()->where('status', 'pending')->count());

        // Pelanggan ternyata sempat membayar tagihan lama: dana masuk tetap dicatat sebagai pelunasan
        $this->sendWebhook($first->fresh(), 'paid')->assertOk();
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame('expired', $second->fresh()->status, 'Tagihan lain ditutup agar tidak dibayar dua kali.');
    }

    public function test_bayar_ganda_tidak_membuat_lunas_dua_kali_dan_ditandai_refund(): void
    {
        [$order, $first] = $this->placeOrder();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer']);
        $second = $order->payments()->latest('id')->first();

        $this->sendWebhook($second, 'paid')->assertOk();
        $this->sendWebhook($first->fresh(), 'paid')->assertOk();

        $this->assertSame(1, $order->payments()->where('status', 'paid')->count());
        $this->assertTrue($first->fresh()->needsRefund());
        $this->assertFalse($second->fresh()->needsRefund());
    }

    public function test_tagihan_lewat_batas_waktu_tampil_kadaluarsa(): void
    {
        [$order, $payment] = $this->placeOrder();
        $payment->update(['expires_at' => now()->subMinute()]);

        $this->assertSame('expired', $payment->fresh()->displayStatus());
        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.show', $order))
            ->assertSee('Pembayaran Kadaluarsa')
            ->assertSee('Buat Tagihan Baru');
        $this->actingAs($this->pelanggan)->get($payment->checkout_url)->assertDontSee('name="result"', false);
    }

    public function test_order_yang_sudah_lunas_tidak_bisa_dibayar_lagi(): void
    {
        [$order, $payment] = $this->placeOrder();
        $this->sendWebhook($payment, 'paid');

        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer'])
            ->assertSessionHas('error');

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_pelanggan_tidak_bisa_membuat_tagihan_untuk_order_orang_lain(): void
    {
        [$order] = $this->placeOrder();

        $this->actingAs($this->makeUser())->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer'])
            ->assertForbidden();
    }

    // ── Konfirmasi manual kasir ─────────────────────────────────────

    public function test_konfirmasi_kasir_melunasi_tagihan_yang_ada_tanpa_baris_baru(): void
    {
        [$order, $payment] = $this->placeOrder();
        $pegawai = $this->makeUser(UserRole::PEGAWAI);

        $this->actingAs($pegawai)->post(route('pegawai.orders.payment', $order), ['method' => 'cash'])
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('cash', $payment->method);
        $this->assertSame($pegawai->id, $payment->meta['confirmed_by']);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_konfirmasi_kedua_ditolak_dan_tidak_menimpa_metode(): void
    {
        [$order, $payment] = $this->placeOrder();
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->post(route('admin.orders.payment', $order), ['method' => 'cash']);
        $this->actingAs($admin)->post(route('admin.orders.payment', $order), ['method' => 'transfer'])
            ->assertSessionHas('error');

        $this->assertSame('cash', $payment->fresh()->method);

        $this->sendWebhook($payment->fresh(), 'paid')->assertOk();
        $this->assertSame(1, $order->payments()->where('status', 'paid')->count());
    }

    public function test_walk_in_kasir_tercatat_sebagai_pembayaran_manual(): void
    {
        $service = $this->makeService('Self Service');
        $pegawai = $this->makeUser(UserRole::PEGAWAI);

        $this->actingAs($pegawai)->post(route('pegawai.orders.store'), [
            'service_id' => $service->id, 'machine_qty' => 1, 'customer_name' => 'Lunas', 'payment_method' => 'cash', 'is_paid' => '1',
        ]);
        $this->actingAs($pegawai)->post(route('pegawai.orders.store'), [
            'service_id' => $service->id, 'machine_qty' => 1, 'customer_name' => 'Belum', 'payment_method' => 'transfer',
        ]);

        $paid = Order::where('customer_name', 'Lunas')->first()->payments()->first();
        $unpaid = Order::where('customer_name', 'Belum')->first()->payments()->first();

        $this->assertSame(['manual', 'paid'], [$paid->gateway, $paid->status]);
        $this->assertSame(['manual', 'pending'], [$unpaid->gateway, $unpaid->status]);
    }

    // ── Laporan admin ───────────────────────────────────────────────

    public function test_filter_belum_lunas_memuat_tagihan_gagal_dan_tidak_memuat_yang_lunas(): void
    {
        [$failedOrder, $failed] = $this->placeOrder();
        $failed->update(['status' => 'failed']);
        [$paidOrder, $paid] = $this->placeOrder();
        $this->sendWebhook($paid, 'paid');

        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->get(route('admin.orders.index', ['payment_status' => 'pending']))
            ->assertSee($failedOrder->order_code)
            ->assertDontSee($paidOrder->order_code);

        $this->actingAs($admin)->get(route('admin.orders.index', ['payment_status' => 'paid']))
            ->assertSee($paidOrder->order_code)
            ->assertDontSee($failedOrder->order_code);
    }
}
