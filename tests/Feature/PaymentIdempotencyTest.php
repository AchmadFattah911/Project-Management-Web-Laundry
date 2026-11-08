<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\IdempotencyException;
use App\Models\Checkout;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use App\Payments\PaymentCharge;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 5: 1 upaya bayar = 1 token pembayaran dari server; token yang sama hanya diproses 1x.
 */
class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    private Checkout $checkout;

    /** Gateway dummy yang menghitung berapa kali tagihan benar-benar dibuat. */
    private object $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new class('test-secret') extends DummyGateway {
            public int $calls = 0;

            public array $keys = [];

            public bool $fail = false;

            public function createCharge(Payment $payment, string $idempotencyKey): PaymentCharge
            {
                $this->calls++;
                $this->keys[] = $idempotencyKey;

                if ($this->fail) {
                    throw new \RuntimeException('Gateway timeout');
                }

                return parent::createCharge($payment, $idempotencyKey);
            }
        };
        $this->app->instance(DummyGateway::class, $this->gateway);

        $this->pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $this->checkout = $this->makeCheckout($this->pelanggan);
    }

    private function makeCheckout(User $user): Checkout
    {
        $a = $this->makeService(attributes: ['price' => 50000]);
        $b = $this->makeService(attributes: ['price' => 15000]);

        $this->actingAs($user)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $a->id, 'qty' => 2, 'item_type' => 'pakaian'],
            ['service_id' => $b->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]]);
        $this->actingAs($user)->post(route('pelanggan.checkout.store'), [
            'cart_items'       => $user->cartItems()->pluck('id')->all(),
            'contact_phone'    => '081234567890',
            'drop_off_date'    => now()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ]);

        return Checkout::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function pay(string $method = 'transfer', ?User $user = null)
    {
        return $this->actingAs($user ?? $this->pelanggan)->post(route('pelanggan.pembayaran.pay', $this->checkout), [
            'payment_method' => $method,
        ]);
    }

    private function payJson(string $method = 'transfer')
    {
        return $this->actingAs($this->pelanggan)->postJson(
            route('pelanggan.pembayaran.pay', $this->checkout),
            ['payment_method' => $method],
        );
    }

    private function webhook(Payment $payment, string $status, ?string $eventId = null)
    {
        $n = $this->gateway->buildNotification($payment, $status, $eventId);

        return $this->call('POST', route('payments.webhook', 'dummy'), [], [], [], [
            'CONTENT_TYPE'           => 'application/json',
            'HTTP_X_DUMMY_SIGNATURE' => $n['headers'][DummyGateway::SIGNATURE_HEADER],
        ], $n['body']);
    }

    private function token(string $method = 'transfer'): string
    {
        return app(PaymentService::class)->paymentToken($this->checkout->fresh(), $method);
    }

    // ── Token dihitung server: 1 pembelian = 1 token = 1 tagihan ────

    public function test_token_dihitung_server_bukan_dari_halaman(): void
    {
        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertOk()
            ->assertDontSee('idempotency_key', false);

        $this->assertSame($this->token(), $this->token(), 'Dihitung ulang berkali-kali tetap token yang sama.');
        $this->assertNotSame($this->token('transfer'), $this->token('e-wallet'));

        $lain = $this->makeCheckout($this->makeUser());
        $this->assertNotSame($this->token(), app(PaymentService::class)->paymentToken($lain, 'transfer'));
    }

    public function test_tiga_request_bayar_hanya_membuat_satu_tagihan(): void
    {
        $token = $this->token();

        $responses = [$this->pay(), $this->pay(), $this->pay()];

        $payment = Payment::sole();
        foreach ($responses as $response) {
            $response->assertRedirect($payment->checkout_url);
        }

        $this->assertSame(1, $this->gateway->calls, 'Gateway hanya boleh dipanggil sekali.');
        $this->assertSame([$token], $this->gateway->keys, 'Token diteruskan ke gateway.');
        $this->assertSame($token, $payment->idempotency_key);
        $this->assertSame($this->checkout->id, $payment->checkout_id);
        $this->assertNull($payment->order_id);
        $this->assertEquals(115000, (float) $payment->amount);

        $record = IdempotencyKey::sole();
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, $record->status);
        $this->assertSame($payment->id, $record->payment_id);
    }

    public function test_reload_halaman_di_antara_request_tidak_membuat_tagihan_baru(): void
    {
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))->assertOk();
            $this->pay();
        }

        $this->assertSame('pending', Payment::sole()->status);
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_key_kiriman_browser_tidak_bisa_memaksa_tagihan_baru(): void
    {
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.pay', $this->checkout), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method'  => 'transfer',
            ]);
        }

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_api_mengembalikan_tagihan_yang_sama_saat_diulang(): void
    {
        $this->payJson()->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('replayed', false);

        $replay = $this->payJson()->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('replayed', true);

        $this->assertSame(Payment::sole()->reference, $replay->json('data.reference'));
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_tiga_notifikasi_lunas_hanya_melunasi_sekali(): void
    {
        $this->pay();
        $payment = Payment::sole();

        foreach (['EVT-1', 'EVT-2', 'EVT-3'] as $event) {
            $this->webhook($payment, 'paid', $event)->assertOk();
        }

        $this->assertSame(1, Payment::where('status', 'paid')->count());
        $this->assertFalse($payment->fresh()->needsRefund());
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
    }

    public function test_token_milik_pelanggan_lain_ditolak(): void
    {
        $token = $this->token();
        $this->pay();

        $this->expectException(IdempotencyException::class);
        app(IdempotencyService::class)->run($token, 'checkout-payment', $this->makeUser(), [
            'checkout_id' => $this->checkout->id, 'payment_method' => 'transfer',
        ], fn () => $this->fail('Callback tidak boleh dijalankan.'));
    }

    public function test_token_yang_sedang_diproses_request_lain_ditolak(): void
    {
        // Request pertama sedang berjalan (belum selesai) saat request kedua datang
        app(IdempotencyService::class)->run($this->token(), 'checkout-payment', $this->pelanggan, [
            'checkout_id' => $this->checkout->id, 'payment_method' => 'transfer',
        ], function () {
            $this->payJson()->assertStatus(409);
            $this->pay()->assertSessionHas('error', 'Pembayaran Anda sedang diproses. Tunggu beberapa detik lalu muat ulang halaman.');

            return ['payment_id' => null];
        });

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, $this->gateway->calls);
    }

    public function test_metode_lain_tidak_boleh_menyalip_tagihan_yang_sedang_dibuat(): void
    {
        // Request "transfer" sudah menyimpan tagihan tetapi masih menunggu respons gateway
        $inFlight = $this->checkout->payments()->create([
            'amount'          => 115000,
            'method'          => 'transfer',
            'gateway'         => 'dummy',
            'status'          => 'pending',
            'idempotency_key' => $this->token(),
        ]);

        $this->payJson('e-wallet')->assertStatus(409);

        $this->assertSame('pending', $inFlight->fresh()->status);
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, $this->gateway->calls);
    }

    public function test_token_processing_yang_macet_boleh_diambil_alih_setelah_lock_habis(): void
    {
        IdempotencyKey::create([
            'key'          => $this->token(),
            'user_id'      => $this->pelanggan->id,
            'scope'        => 'checkout-payment',
            'request_hash' => $this->hashFor('transfer'),
            'status'       => IdempotencyKey::STATUS_PROCESSING,
            'locked_until' => now()->subSecond(),
        ]);

        $this->pay()->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, IdempotencyKey::sole()->status);
    }

    // ── Gateway error: retry aman, pelanggan tidak ditagih dobel ─────

    public function test_gateway_error_lalu_retry_memakai_tagihan_dan_token_yang_sama(): void
    {
        $token = $this->token();

        $this->gateway->fail = true;
        $this->pay()->assertSessionHas('error');

        $payment = Payment::sole();
        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->reference);
        $this->assertSame(IdempotencyKey::STATUS_FAILED, IdempotencyKey::sole()->status);

        $this->gateway->fail = false;
        $this->pay()->assertRedirect();

        $this->assertSame(1, Payment::count(), 'Retry tidak boleh membuat baris tagihan baru.');
        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reference);
        $this->assertSame([$token, $token], $this->gateway->keys, 'Gateway menerima token yang sama sehingga tidak menagih dua kali.');
    }

    // ── Token baru hanya untuk upaya bayar yang memang baru ─────────

    public function test_ganti_metode_membuat_tagihan_baru_dan_menutup_yang_lama(): void
    {
        $this->pay();
        $this->pay('e-wallet');

        $this->assertSame(2, Payment::count());
        $this->assertSame('e-wallet', Payment::where('status', 'pending')->sole()->method);

        // Kembali ke metode awal tidak boleh memutar ulang tagihan yang sudah ditutup
        $this->pay('transfer')->assertRedirect(Payment::latest('id')->first()->checkout_url);

        $this->assertSame(3, Payment::count());
        $this->assertSame('transfer', Payment::where('status', 'pending')->sole()->method);
    }

    public function test_tagihan_gagal_bisa_dibayar_ulang_dengan_token_baru(): void
    {
        $this->pay();
        $first = Payment::sole();
        $this->webhook($first, 'failed')->assertOk();

        $this->pay();

        $this->assertSame(2, Payment::count());
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame(1, Payment::where('status', 'pending')->count());
        $this->assertSame(2, $this->gateway->calls);
    }

    public function test_tagihan_lewat_batas_waktu_diganti_tagihan_baru(): void
    {
        $this->pay();
        $first = Payment::sole();
        $first->update(['expires_at' => now()->subMinute()]);

        $this->pay();
        $this->pay();

        $this->assertSame(2, Payment::count());
        $this->assertSame('expired', $first->fresh()->status);
        $this->assertSame(2, $this->gateway->calls);
    }

    public function test_transaksi_lunas_tidak_bisa_dibayar_lagi(): void
    {
        $this->pay();
        $this->webhook(Payment::sole(), 'paid')->assertOk();

        $this->pay()->assertSessionHas('error', 'Transaksi ini sudah lunas.');

        $this->assertSame(1, Payment::count());
    }

    public function test_metode_pembayaran_divalidasi(): void
    {
        $this->pay('bitcoin')->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::count());
    }

    public function test_pelanggan_lain_tidak_bisa_membayar_transaksi_orang_lain(): void
    {
        $this->pay(user: $this->makeUser())->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    // ── Webhook idempotent + status transaksi ───────────────────────

    public function test_notifikasi_lunas_melunasi_transaksi_dan_semua_ordernya(): void
    {
        $this->pay();
        $this->webhook(Payment::sole(), 'paid')->assertOk();

        $this->checkout->refresh()->load('orders');
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->status);
        $this->assertTrue($this->checkout->orders->every->isPaid());
        $this->assertSame(Payment::sole()->id, $this->checkout->orders->first()->paymentForDisplay()->id);
    }

    public function test_notifikasi_dengan_event_sama_hanya_diterapkan_sekali(): void
    {
        $this->pay();
        $payment = Payment::sole();

        $this->webhook($payment, 'failed', 'EVT-SAMA')->assertOk();
        $this->webhook($payment, 'failed', 'EVT-SAMA')->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_simulasi_dummy_kirim_notifikasi_dua_kali_diproses_sekali(): void
    {
        $this->pay();
        $payment = Payment::sole();

        $this->actingAs($this->pelanggan)
            ->post(route('payments.dummy.simulate', $payment->reference), ['result' => 'paid', 'duplicate' => '1'])
            ->assertRedirect(route('pelanggan.pembayaran.selesai', $this->checkout))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'dikirim 2x, diproses 1x'));

        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_halaman_checkout_dummy_untuk_tagihan_transaksi(): void
    {
        $this->pay();

        $this->actingAs($this->pelanggan)->get(Payment::sole()->checkout_url)
            ->assertOk()
            ->assertSee($this->checkout->code)
            ->assertSee('Kirim notifikasi webhook 2x');
    }

    // ── Kasir & jalur lama ──────────────────────────────────────────

    public function test_konfirmasi_kasir_untuk_order_transaksi_melunasi_seluruh_transaksi_sekali(): void
    {
        $this->pay();
        $admin = $this->makeUser(UserRole::ADMIN);
        $order = $this->checkout->orders()->first();

        $this->actingAs($admin)->post(route('admin.orders.payment', $order), ['method' => 'cash']);
        $this->actingAs($admin)->post(route('admin.orders.payment', $this->checkout->orders()->latest('id')->first()), ['method' => 'transfer']);

        $paid = Payment::where('status', 'paid')->sole();
        $this->assertSame('cash', $paid->method);
        $this->assertEquals(115000, (float) $paid->amount);
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertTrue($this->checkout->orders()->get()->every->isPaid());
    }

    public function test_order_transaksi_tidak_bisa_dibayar_lewat_jalur_order_tunggal(): void
    {
        $order = $this->checkout->orders()->first();

        $this->actingAs($this->pelanggan)->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer'])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    public function test_key_lama_dibersihkan_oleh_prune(): void
    {
        $this->pay();
        IdempotencyKey::query()->update(['created_at' => now()->subDays(31)]);

        $this->artisan('model:prune', ['--model' => [IdempotencyKey::class]])->assertSuccessful();

        $this->assertSame(0, IdempotencyKey::count());
        $this->assertSame(1, Payment::count(), 'Data pembayaran tidak ikut terhapus.');
    }

    private function hashFor(string $method): string
    {
        $payload = ['checkout_id' => $this->checkout->id, 'payment_method' => $method];
        ksort($payload);

        return hash('sha256', 'checkout-payment|' . json_encode($payload));
    }
}
