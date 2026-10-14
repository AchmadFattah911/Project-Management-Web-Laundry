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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 5: 1 request pembayaran = 1 idempotency key; key yang sama hanya diproses 1x.
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
            'contact_phone'    => '081234567890',
            'drop_off_date'    => now()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ]);

        return Checkout::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function pay(string $key, string $method = 'transfer', ?User $user = null)
    {
        return $this->actingAs($user ?? $this->pelanggan)->post(route('pelanggan.pembayaran.pay', $this->checkout), [
            'idempotency_key' => $key,
            'payment_method'  => $method,
        ]);
    }

    private function payJson(string $key, string $method = 'transfer')
    {
        return $this->actingAs($this->pelanggan)->postJson(
            route('pelanggan.pembayaran.pay', $this->checkout),
            ['payment_method' => $method],
            ['Idempotency-Key' => $key],
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

    // ── Key yang sama hanya diproses 1x ─────────────────────────────

    public function test_halaman_pembayaran_membawa_idempotency_key_unik(): void
    {
        $first = $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))->getContent();
        $second = $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))->getContent();

        preg_match('/name="idempotency_key" value="([^"]+)"/', $first, $a);
        preg_match('/name="idempotency_key" value="([^"]+)"/', $second, $b);

        $this->assertTrue(Str::isUuid($a[1] ?? ''));
        $this->assertNotSame($a[1], $b[1]);
    }

    public function test_request_bayar_ganda_dengan_key_sama_hanya_membuat_satu_tagihan(): void
    {
        $key = (string) Str::uuid();

        $first = $this->pay($key);
        $second = $this->pay($key);

        $payment = Payment::sole();
        $first->assertRedirect($payment->checkout_url);
        $second->assertRedirect($payment->checkout_url);

        $this->assertSame(1, $this->gateway->calls, 'Gateway hanya boleh dipanggil sekali.');
        $this->assertSame([$key], $this->gateway->keys, 'Key diteruskan ke gateway.');
        $this->assertSame($key, $payment->idempotency_key);
        $this->assertSame($this->checkout->id, $payment->checkout_id);
        $this->assertNull($payment->order_id);
        $this->assertEquals(115000, (float) $payment->amount);

        $record = IdempotencyKey::sole();
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, $record->status);
        $this->assertSame($payment->id, $record->payment_id);
    }

    public function test_api_dengan_header_idempotency_key_mengembalikan_hasil_tersimpan(): void
    {
        $key = 'payment-abc-123-xyz-789';

        $this->payJson($key)->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('replayed', false);

        $replay = $this->payJson($key)->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('replayed', true);

        $this->assertSame(Payment::sole()->reference, $replay->json('data.reference'));
        $this->assertSame(1, $this->gateway->calls);
    }

    public function test_key_sama_dengan_isi_request_berbeda_ditolak(): void
    {
        $key = (string) Str::uuid();
        $this->pay($key, 'transfer');

        $this->payJson($key, 'e-wallet')->assertStatus(422);
        $this->pay($key, 'e-wallet')->assertSessionHas('error');

        $this->assertSame(1, Payment::count());
        $this->assertSame('transfer', Payment::sole()->method);
    }

    public function test_key_milik_pelanggan_lain_ditolak(): void
    {
        $key = (string) Str::uuid();
        $this->pay($key);

        $this->expectException(IdempotencyException::class);
        app(IdempotencyService::class)->run($key, 'checkout-payment', $this->makeUser(), [
            'checkout_id' => $this->checkout->id, 'payment_method' => 'transfer',
        ], fn () => $this->fail('Callback tidak boleh dijalankan.'));
    }

    public function test_key_yang_sedang_diproses_request_lain_ditolak(): void
    {
        $key = (string) Str::uuid();

        // Request pertama sedang berjalan (belum selesai) saat request kedua datang
        app(IdempotencyService::class)->run($key, 'checkout-payment', $this->pelanggan, [
            'checkout_id' => $this->checkout->id, 'payment_method' => 'transfer',
        ], function () use ($key) {
            $this->payJson($key)->assertStatus(409);
            $this->pay($key)->assertSessionHas('error', 'Pembayaran Anda sedang diproses. Tunggu beberapa detik lalu muat ulang halaman.');

            return ['payment_id' => null];
        });

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, $this->gateway->calls);
    }

    public function test_key_processing_yang_macet_boleh_diambil_alih_setelah_lock_habis(): void
    {
        $key = (string) Str::uuid();
        $this->pay($key);
        IdempotencyKey::query()->delete();
        Payment::query()->delete();

        IdempotencyKey::create([
            'key'          => $key,
            'user_id'      => $this->pelanggan->id,
            'scope'        => 'checkout-payment',
            'request_hash' => $this->hashFor('transfer'),
            'status'       => IdempotencyKey::STATUS_PROCESSING,
            'locked_until' => now()->subSecond(),
        ]);

        $this->pay($key)->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, IdempotencyKey::sole()->status);
    }

    // ── Gateway error: retry aman, pelanggan tidak ditagih dobel ─────

    public function test_gateway_error_lalu_retry_dengan_key_sama_memakai_tagihan_yang_sama(): void
    {
        $key = (string) Str::uuid();

        $this->gateway->fail = true;
        $this->pay($key)->assertSessionHas('error');

        $payment = Payment::sole();
        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->reference);
        $this->assertSame(IdempotencyKey::STATUS_FAILED, IdempotencyKey::sole()->status);

        $this->gateway->fail = false;
        $this->pay($key)->assertRedirect();

        $this->assertSame(1, Payment::count(), 'Retry tidak boleh membuat baris tagihan baru.');
        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reference);
        $this->assertSame([$key, $key], $this->gateway->keys, 'Gateway menerima key yang sama sehingga tidak menagih dua kali.');
    }

    public function test_key_baru_membuat_tagihan_baru_dan_menutup_yang_lama(): void
    {
        $this->pay((string) Str::uuid());
        $this->pay((string) Str::uuid(), 'e-wallet');

        $this->assertSame(2, Payment::count());
        $this->assertSame(1, Payment::where('status', 'pending')->count());
        $this->assertSame('e-wallet', Payment::where('status', 'pending')->sole()->method);
    }

    public function test_transaksi_lunas_tidak_bisa_dibayar_lagi(): void
    {
        $this->pay((string) Str::uuid());
        $this->webhook(Payment::sole(), 'paid')->assertOk();

        $this->pay((string) Str::uuid())->assertSessionHas('error', 'Transaksi ini sudah lunas.');

        $this->assertSame(1, Payment::count());
    }

    public function test_key_wajib_dan_formatnya_divalidasi(): void
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.pay', $this->checkout), ['payment_method' => 'transfer'])
            ->assertSessionHasErrors('idempotency_key');
        $this->pay('pendek')->assertSessionHasErrors('idempotency_key');
        $this->pay('key dengan spasi <script>')->assertSessionHasErrors('idempotency_key');

        $this->assertSame(0, Payment::count());
    }

    public function test_pelanggan_lain_tidak_bisa_membayar_transaksi_orang_lain(): void
    {
        $this->pay((string) Str::uuid(), user: $this->makeUser())->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    // ── Webhook idempotent + status transaksi ───────────────────────

    public function test_notifikasi_lunas_melunasi_transaksi_dan_semua_ordernya(): void
    {
        $this->pay((string) Str::uuid());
        $this->webhook(Payment::sole(), 'paid')->assertOk();

        $this->checkout->refresh()->load('orders');
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->status);
        $this->assertTrue($this->checkout->orders->every->isPaid());
        $this->assertSame(Payment::sole()->id, $this->checkout->orders->first()->paymentForDisplay()->id);
    }

    public function test_notifikasi_dengan_event_sama_hanya_diterapkan_sekali(): void
    {
        $this->pay((string) Str::uuid());
        $payment = Payment::sole();

        $this->webhook($payment, 'failed', 'EVT-SAMA')->assertOk();
        $this->webhook($payment, 'failed', 'EVT-SAMA')->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_simulasi_dummy_kirim_notifikasi_dua_kali_diproses_sekali(): void
    {
        $this->pay((string) Str::uuid());
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
        $this->pay((string) Str::uuid());

        $this->actingAs($this->pelanggan)->get(Payment::sole()->checkout_url)
            ->assertOk()
            ->assertSee($this->checkout->code)
            ->assertSee('Kirim notifikasi webhook 2x');
    }

    // ── Kasir & jalur lama ──────────────────────────────────────────

    public function test_konfirmasi_kasir_untuk_order_transaksi_melunasi_seluruh_transaksi_sekali(): void
    {
        $this->pay((string) Str::uuid());
        $pegawai = $this->makeUser(UserRole::PEGAWAI);
        $order = $this->checkout->orders()->first();

        $this->actingAs($pegawai)->post(route('pegawai.orders.payment', $order), ['method' => 'cash']);
        $this->actingAs($pegawai)->post(route('pegawai.orders.payment', $this->checkout->orders()->latest('id')->first()), ['method' => 'transfer']);

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
        $this->pay((string) Str::uuid());
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
