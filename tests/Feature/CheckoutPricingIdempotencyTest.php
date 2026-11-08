<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 2 pembayaran: harga & total dihitung di backend (integer rupiah), snapshot item pesanan,
 * batas waktu bayar, dan submit ganda checkout yang selalu bermuara ke 1 transaksi.
 */
class CheckoutPricingIdempotencyTest extends TestCase
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
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji', 'slug' => 'cuci-lipat-uji', 'price' => 35000]);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja Uji', 'slug' => 'cuci-saja-uji', 'price' => 15000]);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $lipat->id, 'qty' => 2, 'item_type' => 'pakaian'],
            ['service_id' => $saja->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasNoErrors();

        return [$lipat, $saja];
    }

    private function detail(array $overrides = []): array
    {
        return [
            'cart_items'       => $this->pelanggan->cartItems()->pluck('id')->all(),
            'drop_off_date'    => now()->addDay()->toDateString(),
            'notes'            => 'Antar sore',
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
            ...$overrides,
        ];
    }

    private function checkout(array $detail)
    {
        return $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), $detail);
    }

    // Tes wajib #9
    public function test_field_harga_dan_total_palsu_dari_client_diabaikan(): void
    {
        $this->fillCart();

        $this->checkout($this->detail([
            'total_amount' => 1,
            'total'        => 1,
            'subtotal'     => 1,
            'discount'     => 999999,
            'price'        => 1,
            'unit_price'   => 1,
            'total_price'  => 1,
            'items'        => [['price' => 1, 'qty' => 99]],
        ]))->assertSessionHasNoErrors();

        $checkout = Checkout::sole();
        $this->assertSame(85000, $checkout->totalRupiah()); // 2 × 35.000 + 15.000

        $orders = $checkout->orders()->orderBy('id')->get();
        $this->assertEquals(35000, (float) $orders[0]->unit_price);
        $this->assertEquals(70000, (float) $orders[0]->total_price);
        $this->assertSame(2, $orders[0]->machine_qty);
        $this->assertEquals(15000, (float) $orders[1]->total_price);
    }

    public function test_snapshot_nama_dan_harga_tidak_berubah_saat_layanan_diubah(): void
    {
        [$lipat] = $this->fillCart();
        $this->checkout($this->detail());

        $lipat->update(['name' => 'Nama Baru', 'price' => 99000]);

        $order = Order::where('service_id', $lipat->id)->sole()->fresh('service');
        $this->assertSame('Cuci Lipat Uji', $order->service_name_snapshot);
        $this->assertSame('Cuci Lipat Uji', $order->serviceName());
        $this->assertEquals(35000, (float) $order->unit_price);
        $this->assertSame(85000, Checkout::sole()->totalRupiah());
    }

    public function test_order_lama_tanpa_snapshot_memakai_nama_layanan_saat_ini(): void
    {
        $service = $this->makeService(attributes: ['name' => 'Layanan Lama']);
        $order = $this->makeOrder($this->pelanggan, $service);
        $order->forceFill(['service_name_snapshot' => null])->save();

        $this->assertSame('Layanan Lama', $order->fresh('service')->serviceName());
    }

    public function test_batas_waktu_bayar_diisi_saat_transaksi_dibuat(): void
    {
        config(['payment.expiry_minutes' => 45]);
        $this->freezeSecond();
        $this->fillCart();

        $this->checkout($this->detail());

        $checkout = Checkout::sole();
        $this->assertTrue($checkout->payment_deadline->equalTo(now()->addMinutes(45)));
        $this->assertNull($checkout->paid_at);
        $this->assertNull($checkout->pending_reason);
    }

    public function test_submit_ulang_token_dan_isi_sama_diarahkan_ke_transaksi_yang_sama(): void
    {
        $this->fillCart();
        $detail = $this->detail();

        $this->checkout($detail)->assertSessionHas('success');
        $checkout = Checkout::sole();

        foreach ([1, 2] as $_) {
            $this->checkout($detail)
                ->assertRedirect(route('pelanggan.pembayaran.show', $checkout))
                ->assertSessionHas('status', fn ($msg) => str_contains($msg, $checkout->code) && str_contains($msg, 'sudah dibuat'));
        }

        $this->assertSame(1, Checkout::count());
        $this->assertSame(2, Order::count());

        $record = IdempotencyKey::sole();
        $this->assertSame(CheckoutService::IDEMPOTENCY_SCOPE . ':' . $detail['submission_token'], $record->key);
        $this->assertSame($this->pelanggan->id, $record->user_id);
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, $record->status);
        $this->assertSame(['checkout_code' => $checkout->code], $record->response);
        $this->assertSame(64, strlen($record->request_hash));
    }

    public function test_token_sama_isi_berbeda_diarahkan_ke_transaksi_lama_tanpa_mengubahnya(): void
    {
        $this->fillCart();
        $detail = $this->detail();
        $this->checkout($detail);
        $checkout = Checkout::sole();

        $this->checkout([...$detail, 'drop_off_date' => now()->addDays(3)->toDateString(), 'notes' => 'Diubah'])
            ->assertRedirect(route('pelanggan.pembayaran.show', $checkout))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'perubahan terakhir tidak disimpan'));

        $fresh = $checkout->fresh();
        $this->assertSame(1, Checkout::count());
        $this->assertSame('Antar sore', $fresh->notes);
        $this->assertTrue($fresh->drop_off_date->isSameDay(now()->addDay()));
    }

    public function test_urutan_item_dan_spasi_catatan_tidak_dianggap_isi_berbeda(): void
    {
        $this->fillCart();
        $detail = $this->detail();
        $this->checkout($detail);

        $this->checkout([...$detail, 'cart_items' => array_reverse($detail['cart_items']), 'notes' => '  Antar sore  '])
            ->assertSessionHas('status');

        $this->assertSame(1, Checkout::count());
    }

    public function test_submit_ulang_setelah_no_hp_tersimpan_ke_akun_tetap_request_yang_sama(): void
    {
        $this->pelanggan->update(['phone' => null]);
        $this->fillCart();
        $detail = $this->detail(['contact_phone' => '081211112222']);

        $this->checkout($detail)->assertSessionHas('success');
        // Akun kini punya nomor, form kirim ulang otomatis tidak lagi membawa contact_phone
        $this->checkout($detail)->assertSessionHas('status');

        $this->assertSame(1, Checkout::count());
    }

    public function test_gagal_validasi_keranjang_tidak_meninggalkan_catatan_idempotency(): void
    {
        $this->checkout($this->detail(['cart_items' => [999999]]))->assertSessionHasErrors('cart');

        $this->assertSame(0, IdempotencyKey::count());
        $this->assertSame(0, Checkout::count());
    }
}
