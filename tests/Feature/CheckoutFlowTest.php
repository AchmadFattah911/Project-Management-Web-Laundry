<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 4: keranjang → langkah detail → 1 transaksi (checkout) berisi beberapa order.
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
    }

    private function fillCart(): array
    {
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji', 'slug' => 'cuci-lipat-uji', 'price' => 50000]);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja Uji', 'slug' => 'cuci-saja-uji', 'price' => 15000]);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $lipat->id, 'qty' => 2, 'item_type' => 'sprei', 'notes' => 'Wangi'],
            ['service_id' => $saja->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasNoErrors();

        return [$lipat, $saja];
    }

    private function detail(array $overrides = []): array
    {
        return [
            'contact_phone'    => '0812-3456-7890',
            'drop_off_date'    => now()->addDay()->toDateString(),
            'notes'            => 'Antar sore',
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
            ...$overrides,
        ];
    }

    public function test_langkah_detail_tampil_dengan_data_akun_dan_token_form(): void
    {
        $this->fillCart();

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pesan.index', ['langkah' => 'detail']))
            ->assertOk()
            ->assertSee('2. Detail & Konfirmasi Pesanan', false)
            ->assertSee('value="081234567890"', false)
            ->assertSee('name="submission_token"', false)
            ->assertSee('Rp 115.000')
            ->assertSee('Ubah isi keranjang');
    }

    public function test_langkah_detail_dengan_keranjang_kosong_dikembalikan(): void
    {
        $this->actingAs($this->pelanggan)->get(route('pelanggan.pesan.index', ['langkah' => 'detail']))
            ->assertRedirect(route('pelanggan.pesan.index'))
            ->assertSessionHas('error');
    }

    public function test_checkout_membuat_satu_transaksi_berisi_order_per_layanan(): void
    {
        [$lipat, $saja] = $this->fillCart();

        $response = $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());

        $checkout = Checkout::sole();
        $response->assertRedirect(route('pelanggan.pembayaran.show', $checkout));

        $this->assertMatchesRegularExpression('/^TRX-[A-Z0-9]{8}$/', $checkout->code);
        $this->assertSame(Checkout::STATUS_AWAITING, $checkout->status);
        $this->assertSame('081234567890', $checkout->contact_phone);
        $this->assertEquals(115000, (float) $checkout->total_amount);

        $orders = $checkout->orders()->with('orderProcesses')->orderBy('id')->get();
        $this->assertCount(2, $orders);
        $this->assertSame($lipat->id, $orders[0]->service_id);
        $this->assertSame(2, $orders[0]->machine_qty);
        $this->assertEquals(100000, (float) $orders[0]->total_price);
        $this->assertSame('Sprei / Selimut Tipis', $orders[0]->item_type);
        $this->assertSame('Wangi', $orders[0]->notes);
        $this->assertSame(['cuci', 'kering', 'lipat'], $orders[0]->orderProcesses->pluck('process')->all());
        $this->assertSame(['cuci'], $orders[1]->orderProcesses->pluck('process')->all());
        $this->assertTrue($orders->every(fn (Order $o) => $o->user_id === $this->pelanggan->id && $o->status === 'pending'));

        // Keranjang kosong & belum ada pembayaran yang dibuat (dibuat di halaman pembayaran)
        $this->assertSame(0, $this->pelanggan->cartItems()->count());
        $this->assertDatabaseCount('payments', 0);
        $this->assertFalse($orders[0]->isPaid());
    }

    public function test_klik_submit_ganda_dengan_token_sama_hanya_membuat_satu_transaksi(): void
    {
        $this->fillCart();
        $detail = $this->detail();

        $first = $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $detail);
        $second = $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $detail);

        $this->assertSame(1, Checkout::count());
        $this->assertSame(2, Order::count());
        $first->assertRedirect(route('pelanggan.pembayaran.show', Checkout::sole()));
        $second->assertRedirect(route('pelanggan.pembayaran.show', Checkout::sole()));
    }

    public function test_token_milik_pelanggan_lain_ditolak(): void
    {
        $this->fillCart();
        $detail = $this->detail();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $detail);

        $lain = $this->makeUser();
        $service = $this->makeService();
        $this->actingAs($lain)->post(route('pelanggan.cart.store'), ['items' => [['service_id' => $service->id, 'qty' => 1, 'item_type' => 'pakaian']]]);

        $this->actingAs($lain)->post(route('pelanggan.checkout.store'), $detail)
            ->assertSessionHasErrors('submission_token');

        $this->assertSame(1, Checkout::count());
    }

    public function test_validasi_detail_pesanan(): void
    {
        $this->fillCart();

        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail([
            'contact_phone' => '12345',
            'drop_off_date' => now()->subDay()->toDateString(),
            'agree'         => null,
        ]))->assertSessionHasErrors(['contact_phone', 'drop_off_date', 'agree']);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail([
            'drop_off_date' => now()->addDays(8)->toDateString(),
        ]))->assertSessionHasErrors('drop_off_date');

        $this->assertSame(0, Checkout::count());
        $this->assertSame(2, $this->pelanggan->cartItems()->count());
    }

    public function test_keranjang_kosong_tidak_bisa_checkout(): void
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail())
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Checkout::count());
    }

    public function test_layanan_nonaktif_tidak_ikut_dan_tetap_di_keranjang(): void
    {
        [$lipat, $saja] = $this->fillCart();
        $saja->update(['status' => 'tidak_tersedia']);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());

        $checkout = Checkout::sole();
        $this->assertSame([$lipat->id], $checkout->orders()->pluck('service_id')->all());
        $this->assertEquals(100000, (float) $checkout->total_amount);
        $this->assertSame([$saja->id], $this->pelanggan->cartItems()->pluck('service_id')->all());
    }

    public function test_harga_diambil_dari_tarif_saat_checkout(): void
    {
        [$lipat] = $this->fillCart();
        $lipat->update(['price' => 60000]);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());

        $this->assertEquals(135000, (float) Checkout::sole()->total_amount);
    }

    // ── Tahap 8: integrasi order transaksi dengan panel admin/pegawai & detail order ──

    private function paidCheckout(): Checkout
    {
        $this->fillCart();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());
        $checkout = Checkout::sole();

        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.pay', $checkout), [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method'  => 'transfer',
        ]);
        $payment = $checkout->payments()->sole();
        $n = app(\App\Payments\Gateways\DummyGateway::class)->buildNotification($payment, 'paid');
        app(\App\Services\PaymentService::class)->handleNotification('dummy', $n['body'], $n['headers']);

        return $checkout->fresh();
    }

    public function test_filter_status_bayar_admin_mengenali_order_transaksi(): void
    {
        $checkout = $this->paidCheckout();
        $unpaid = $this->makeOrder($this->pelanggan, $this->makeService(), ['order_code' => 'LC-BELUM1']);
        $admin = $this->makeUser(\App\Enums\UserRole::ADMIN);
        $codes = $checkout->orders()->pluck('order_code');

        $paidPage = $this->actingAs($admin)->get(route('admin.orders.index', ['payment_status' => 'paid']))
            ->assertSee($checkout->code)
            ->assertDontSee('LC-BELUM1');
        $codes->each(fn ($code) => $paidPage->assertSee($code));

        $pendingPage = $this->actingAs($admin)->get(route('admin.orders.index', ['payment_status' => 'pending']))
            ->assertSee($unpaid->order_code);
        $codes->each(fn ($code) => $pendingPage->assertDontSee($code));
    }

    public function test_admin_tidak_bisa_menghapus_order_transaksi(): void
    {
        $this->fillCart();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());
        $unpaidCheckoutOrder = Order::firstOrFail();
        $admin = $this->makeUser(\App\Enums\UserRole::ADMIN);

        // Belum lunas pun tidak boleh: total & tagihan transaksi sudah mencakup order ini
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $unpaidCheckoutOrder))->assertForbidden();

        $standalone = $this->makeOrder($this->pelanggan, $this->makeService());
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $standalone))->assertRedirect();
        $this->assertModelMissing($standalone);
    }

    public function test_admin_tidak_bisa_menghapus_order_transaksi_yang_sudah_lunas(): void
    {
        $order = $this->paidCheckout()->orders()->first();

        $this->assertTrue($order->isPaid());
        $this->actingAs($this->makeUser(\App\Enums\UserRole::ADMIN))
            ->delete(route('admin.orders.destroy', $order))->assertForbidden();
    }

    public function test_detail_order_transaksi_mengarahkan_ke_halaman_transaksi(): void
    {
        $this->fillCart();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());
        $checkout = Checkout::sole();

        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.show', $checkout->orders()->first()))
            ->assertOk()
            ->assertSee('Bagian dari transaksi')
            ->assertSee($checkout->code)
            ->assertSee(route('pelanggan.pembayaran.show', $checkout), false)
            ->assertDontSee('name="payment_method"', false);
    }

    public function test_halaman_pembayaran_hanya_untuk_pemilik(): void
    {
        $this->fillCart();
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $this->detail());
        $checkout = Checkout::sole();

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $checkout))
            ->assertOk()
            ->assertSee($checkout->code)
            ->assertSee('Cuci Lipat Uji')
            ->assertSee('Rp 115.000');

        $this->actingAs($this->makeUser())->get(route('pelanggan.pembayaran.show', $checkout))->assertForbidden();
    }
}
