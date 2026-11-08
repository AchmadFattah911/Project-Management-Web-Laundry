<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Services\SnapPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 4 pembayaran: Midtrans Snap sandbox + /bayar idempoten, batal, popup ditutup, pesan lagi.
 * Semua panggilan ke Midtrans dipalsukan (Http::fake); tidak ada request jaringan sungguhan.
 */
class MidtransSnapPaymentTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private const SNAP_URL = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    private User $pelanggan;

    private Checkout $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.enabled' => true, 'payment.default' => 'midtrans', 'payment.expiry_minutes' => 60]);
        $this->freezeSecond();

        $this->pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $this->checkout = $this->makeCheckout();
    }

    private function makeCheckout(): Checkout
    {
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji', 'price' => 35000]);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja Uji', 'price' => 15000]);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $lipat->id, 'qty' => 2, 'item_type' => 'sprei'],
            ['service_id' => $saja->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), [
            'cart_items'       => $this->pelanggan->cartItems()->pluck('id')->all(),
            'drop_off_date'    => now()->addDay()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Checkout::latest('id')->firstOrFail();
    }

    private function fakeSnap(array $responses = []): void
    {
        $sequence = Http::sequence();

        foreach ($responses ?: [null] as $i => $response) {
            $response === null
                ? $sequence->push(['token' => 'snap-token-' . ($i + 1), 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-' . ($i + 1)], 201)
                : $sequence->pushResponse($response);
        }

        Http::fake([self::SNAP_URL => $sequence->whenEmpty(Http::response(['token' => 'snap-token-x', 'redirect_url' => 'https://x'], 201))]);
    }

    private function pay(?User $user = null, array $body = [])
    {
        return $this->actingAs($user ?? $this->pelanggan)->postJson(route('pelanggan.pembayaran.pay', $this->checkout), $body);
    }

    private function snapRequests(): int
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::SNAP_URL)->count();
    }

    // ── /bayar ──────────────────────────────────────────────────

    public function test_bayar_membuat_snap_dengan_nominal_dan_order_id_dari_server(): void
    {
        $this->fakeSnap();

        $this->pay(body: ['gross_amount' => 1, 'total' => 1, 'amount' => 1])
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token-1')
            ->assertJsonPath('data.reference', "{$this->checkout->code}-P1")
            ->assertJsonPath('replayed', false);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::SNAP_URL
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('SB-Mid-server-TEST:'))
                && $body['transaction_details']['order_id'] === "{$this->checkout->code}-P1"
                && $body['transaction_details']['gross_amount'] === 85000
                && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $body['item_details'])) === 85000
                && ! in_array('credit_card', $body['enabled_payments'], true)
                && $body['expiry'] === ['start_time' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s O'), 'unit' => 'minute', 'duration' => 60]
                && str_contains($body['callbacks']['finish'], 'verifikasi=1');
        });

        $payment = Payment::sole();
        $this->assertSame('midtrans', $payment->gateway);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('snap-token-1', $payment->snap_token);
        $this->assertEquals(85000, (float) $payment->amount);
        $this->assertTrue($payment->expires_at->equalTo($this->checkout->fresh()->payment_deadline));

        // /bayar tidak pernah melunasi
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    // Tes wajib #3
    public function test_klik_bayar_tiga_kali_memakai_snap_token_yang_sama(): void
    {
        $this->fakeSnap();

        $responses = collect([1, 2, 3])->map(fn () => $this->pay()->assertSuccessful());

        $this->assertSame(['snap-token-1'], $responses->map(fn ($r) => $r->json('data.snap_token'))->unique()->values()->all());
        $this->assertSame([false, true, true], $responses->map(fn ($r) => $r->json('replayed'))->all());
        $this->assertSame(1, $this->snapRequests());
        $this->assertSame(1, Payment::where('status', Payment::STATUS_PENDING)->count());
        $this->assertSame(1, Payment::count());
    }

    // Revisi (c)
    public function test_snap_gagal_ditandai_failed_dan_percobaan_berikutnya_memakai_nomor_baru(): void
    {
        $this->fakeSnap([Http::response(['error_messages' => ['Server sibuk']], 500)]);

        $this->pay()->assertStatus(422)->assertJsonPath('message', 'Pembayaran gagal disiapkan. Silakan coba lagi beberapa saat lagi.');

        $first = Payment::sole();
        $this->assertSame(Payment::STATUS_FAILED, $first->status);
        $this->assertNull($first->snap_token);
        $this->assertSame('snap_create_failed', $first->meta['closed_reason']);
        $this->assertStringContainsString('Server sibuk', $first->meta['error']);

        $this->pay()->assertCreated()->assertJsonPath('data.reference', "{$this->checkout->code}-P2");

        $this->assertSame(Payment::STATUS_FAILED, $first->fresh()->status);
        $this->assertSame(1, Payment::where('status', Payment::STATUS_PENDING)->count());
    }

    public function test_snap_timeout_tidak_mengunci_transaksi(): void
    {
        $calls = 0;
        Http::fake([self::SNAP_URL => function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['token' => 'snap-token-2', 'redirect_url' => 'https://x'], 201);
        }]);

        $this->pay()->assertStatus(422);
        $this->assertSame(Payment::STATUS_FAILED, Payment::sole()->status);

        $this->pay()->assertCreated()->assertJsonPath('data.snap_token', 'snap-token-2')->assertJsonPath('data.reference', "{$this->checkout->code}-P2");
    }

    public function test_tagihan_yang_sedang_dibuat_dijawab_409_lalu_diambil_alih_jika_macet(): void
    {
        $this->fakeSnap();
        $inFlight = $this->checkout->payments()->create([
            'amount' => 85000, 'method' => 'transfer', 'gateway' => 'midtrans',
            'reference' => "{$this->checkout->code}-P1", 'status' => Payment::STATUS_PENDING,
        ]);

        $this->pay()->assertStatus(409)->assertHeader('Retry-After', '2');
        $this->assertSame(0, $this->snapRequests());

        // Proses yang membuatnya mati di tengah jalan: lewat in_flight_seconds → ditutup, dibuat P2
        $this->travel(46)->seconds();
        $this->pay()->assertCreated()->assertJsonPath('data.reference', "{$this->checkout->code}-P2");

        $this->assertSame(Payment::STATUS_FAILED, $inFlight->fresh()->status);
        $this->assertSame('stale_in_flight', $inFlight->fresh()->meta['closed_reason']);
    }

    public function test_database_menolak_dua_payment_pending_untuk_satu_transaksi(): void
    {
        $attributes = ['amount' => 85000, 'method' => 'transfer', 'gateway' => 'midtrans', 'status' => Payment::STATUS_PENDING];
        $this->checkout->payments()->create($attributes + ['reference' => 'A-P1']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->checkout->payments()->create($attributes + ['reference' => 'A-P2']);
    }

    public function test_tidak_bisa_bayar_setelah_batas_waktu(): void
    {
        $this->fakeSnap();
        $this->travel(61)->minutes();

        $this->pay()->assertStatus(422)->assertJsonPath('message', 'Batas waktu pembayaran sudah lewat. Silakan buat pesanan baru.');
        $this->assertSame(0, $this->snapRequests());
    }

    public function test_transaksi_dibatalkan_tidak_bisa_dibayar(): void
    {
        $this->fakeSnap();
        $this->checkout->update(['status' => Checkout::STATUS_CANCELLED]);

        $this->pay()->assertStatus(422);
        $this->assertSame(0, Payment::count());
    }

    // Tes wajib #10 (transaksi) + revisi (f)
    public function test_transaksi_milik_orang_lain_dijawab_404_di_semua_endpoint(): void
    {
        $this->fakeSnap();
        $lain = $this->makeUser();
        $code = $this->checkout;

        $this->actingAs($lain)->get(route('pelanggan.pembayaran.show', $code))->assertNotFound();
        $this->actingAs($lain)->postJson(route('pelanggan.pembayaran.pay', $code))->assertNotFound();
        $this->actingAs($lain)->post(route('pelanggan.pembayaran.cancel', $code))->assertNotFound();
        $this->actingAs($lain)->post(route('pelanggan.pembayaran.popup-closed', $code))->assertNotFound();
        $this->actingAs($lain)->post(route('pelanggan.pembayaran.reorder', $code))->assertNotFound();

        $this->assertSame(0, Payment::count());
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    // ── Halaman ─────────────────────────────────────────────────

    public function test_halaman_bayar_memuat_snap_js_dengan_client_key_saja(): void
    {
        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertOk()
            ->assertSee('https://app.sandbox.midtrans.com/snap/snap.js', false)
            ->assertSee('SB-Mid-client-TEST', false)
            ->assertDontSee('SB-Mid-server-TEST', false)
            ->assertDontSee('name="payment_method"', false)
            ->assertSee('Batalkan Pesanan');
    }

    public function test_halaman_verifikasi_tidak_mengubah_status(): void
    {
        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', ['checkout' => $this->checkout, 'verifikasi' => 1]))
            ->assertOk()
            ->assertSee('Kami sedang memverifikasi pembayaran Anda.');

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(1, $this->checkout->statusLogs()->count());
    }

    public function test_flag_mati_tidak_memuat_snap(): void
    {
        config(['payment.enabled' => false, 'payment.default' => 'dummy']);

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertOk()
            ->assertDontSee('snap.js', false)
            ->assertSee('name="payment_method"', false);
    }

    // ── C4: tidak ada pelunasan manual untuk transaksi online ───

    public function test_admin_tidak_bisa_melunasi_manual_transaksi_online_saat_midtrans_aktif(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);
        $order = $this->checkout->orders()->first();

        $this->actingAs($admin)->from(route('admin.orders.index'))
            ->post(route('admin.orders.payment', $order), ['method' => 'cash'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak bisa ditandai lunas manual'));

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertFalse($order->fresh()->isPaid());

        // Pesanan kasir (tanpa transaksi online) tetap bisa dilunasi manual
        $walkIn = $this->makeOrder(null, $this->makeService());
        $this->actingAs($admin)->post(route('admin.orders.payment', $walkIn), ['method' => 'cash'])->assertSessionHas('success');
        $this->assertTrue($walkIn->fresh()->isPaid());
    }

    public function test_pesanan_tunggal_lama_tidak_dibayar_lewat_midtrans(): void
    {
        $order = $this->makeOrder($this->pelanggan, $this->makeService());

        $this->actingAs($this->pelanggan)->from(route('pelanggan.orders.show', $order))
            ->post(route('pelanggan.orders.pay', $order), ['payment_method' => 'transfer'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'bayar langsung di outlet'));

        $this->assertSame(0, $order->payments()->count());
    }

    // ── Batal ───────────────────────────────────────────────────

    private function cancel()
    {
        return $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.cancel', $this->checkout));
    }

    public function test_batal_tanpa_tagihan(): void
    {
        Http::fake();

        $this->cancel()->assertRedirect(route('pelanggan.pembayaran.show', $this->checkout))->assertSessionHas('status');

        $this->assertSame(Checkout::STATUS_CANCELLED, $this->checkout->fresh()->status);
        $log = $this->checkout->statusLogs()->get()->last();
        $this->assertSame([Checkout::STATUS_AWAITING, Checkout::STATUS_CANCELLED, OrderStateMachine::ACTOR_PELANGGAN], [$log->from_status, $log->to_status, $log->actor_type]);
        Http::assertNothingSent();
    }

    public function test_batal_menutup_tagihan_pending_di_midtrans(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        Http::fake([
            "https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::response(['status_code' => '201', 'transaction_status' => 'pending']),
            "https://api.sandbox.midtrans.com/v2/{$reference}/expire" => Http::response(['status_code' => '407', 'transaction_status' => 'expire']),
        ]);

        $this->cancel()->assertSessionHas('status');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/v2/{$reference}/expire") && $r->method() === 'POST');
        $this->assertSame(Checkout::STATUS_CANCELLED, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_EXPIRED, Payment::sole()->status);
        $this->assertSame('cancelled_by_customer', Payment::sole()->meta['closed_reason']);
    }

    public function test_batal_ditolak_jika_midtrans_sudah_menerima_pembayaran(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        Http::fake(["https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::response(['status_code' => '200', 'transaction_status' => 'settlement'])]);

        $this->cancel()->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah masuk, sedang diverifikasi'));

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/expire'));
    }

    public function test_batal_dibatalkan_jika_pelanggan_membayar_di_antara_cek_status_dan_expire(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        // Cek status pertama: masih pending. Saat expire dikirim pelanggan sudah membayar, Midtrans menolak (412).
        Http::fake([
            "https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::sequence()
                ->push(['status_code' => '201', 'transaction_status' => 'pending'])
                ->push(['status_code' => '200', 'transaction_status' => 'settlement']),
            "https://api.sandbox.midtrans.com/v2/{$reference}/expire" => Http::response(['status_code' => '412', 'status_message' => 'Merchant cannot modify the status of the transaction'], 412),
        ]);

        $this->cancel()->assertSessionHas('error', SnapPaymentService::ALREADY_PAID_MESSAGE);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/v2/{$reference}/expire"));
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);
        $this->assertSame(0, $this->checkout->statusLogs()->where('to_status', Checkout::STATUS_CANCELLED)->count());
    }

    public function test_batal_ditolak_jika_expire_gagal_tanpa_pembayaran(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        Http::fake([
            "https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::response(['status_code' => '201', 'transaction_status' => 'pending']),
            "https://api.sandbox.midtrans.com/v2/{$reference}/expire" => Http::response(['status_message' => 'error'], 500),
        ]);

        $this->cancel()->assertSessionHas('error', fn ($m) => str_contains($m, 'belum bisa ditutup'));
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    public function test_batal_ditolak_jika_status_midtrans_tidak_bisa_dicek(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        Http::fake(["https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::response(['status_message' => 'error'], 500)]);

        $this->cancel()->assertSessionHas('error');
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    public function test_batal_saat_pelanggan_belum_memilih_metode(): void
    {
        $this->fakeSnap();
        $this->pay()->assertCreated();
        $reference = Payment::sole()->reference;

        // Midtrans belum punya transaksi (Snap dibuka tetapi metode belum dipilih)
        Http::fake(["https://api.sandbox.midtrans.com/v2/{$reference}/status" => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404)]);

        $this->cancel()->assertSessionHas('status');

        $this->assertSame(Checkout::STATUS_CANCELLED, $this->checkout->fresh()->status);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/expire'));
    }

    public function test_transaksi_lunas_tidak_bisa_dibatalkan(): void
    {
        $this->checkout->update(['status' => Checkout::STATUS_PAID]);

        $this->cancel()->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah lunas'));
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
    }

    // ── Popup ditutup ───────────────────────────────────────────

    public function test_popup_ditutup_hanya_mencatat_alasan(): void
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.popup-closed', $this->checkout))->assertNoContent();

        $fresh = $this->checkout->fresh();
        $this->assertSame('popup_ditutup', $fresh->pending_reason);
        $this->assertSame(Checkout::STATUS_AWAITING, $fresh->status);

        // Tidak menimpa "menunggu_transfer" (VA sudah terbit)
        $fresh->update(['pending_reason' => 'menunggu_transfer']);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.popup-closed', $this->checkout))->assertNoContent();
        $this->assertSame('menunggu_transfer', $fresh->fresh()->pending_reason);
    }

    // ── Pesan Lagi (tes wajib #11) ──────────────────────────────

    public function test_pesan_lagi_dari_transaksi_kedaluwarsa_memakai_harga_terbaru(): void
    {
        $this->checkout->update(['status' => Checkout::STATUS_EXPIRED]);
        [$lipat, $saja] = $this->checkout->orders()->orderBy('id')->get()->map->service->all();
        $lipat->update(['price' => 40000]);
        $saja->update(['status' => 'tidak_tersedia']);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.reorder', $this->checkout))
            ->assertRedirect(route('pelanggan.cart.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Cuci Saja Uji'));

        $item = $this->pelanggan->cartItems()->with('service')->sole();
        $this->assertSame($lipat->id, $item->service_id);
        $this->assertSame(2, $item->qty);
        $this->assertSame('sprei', $item->item_type);
        $this->assertEquals(80000, $item->subtotal()); // 2 × harga terbaru 40.000, bukan snapshot 35.000

        $this->actingAs($this->pelanggan)->get(route('pelanggan.cart.index'))->assertSee('Rp 80.000');

        // Transaksi lama tidak berubah
        $this->assertEquals(35000, (float) Order::where('service_id', $lipat->id)->sole()->unit_price);
    }

    public function test_pesan_lagi_hanya_untuk_transaksi_yang_berhenti_tanpa_dibayar(): void
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.reorder', $this->checkout))
            ->assertRedirect(route('pelanggan.cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->pelanggan->cartItems()->count());
    }

    // ── Rate limit ──────────────────────────────────────────────

    public function test_rate_limit_batal_per_pelanggan(): void
    {
        Http::fake();

        foreach (range(1, 10) as $_) {
            $this->cancel()->assertRedirect();
        }

        $this->cancel()->assertStatus(429);
    }

    public function test_polling_status_tidak_menghabiskan_jatah_bayar(): void
    {
        $this->fakeSnap();

        foreach (range(1, 25) as $_) {
            $this->actingAs($this->pelanggan)->getJson(route('pelanggan.pembayaran.status', $this->checkout))->assertOk();
        }

        // Dulu semua route ber-throttle berbagi 1 counter per user (bayar dibatasi 20/menit)
        $this->pay()->assertCreated();
    }
}
