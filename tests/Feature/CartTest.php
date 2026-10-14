<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 2: keranjang pelanggan (tambah banyak layanan sekaligus dari 1 form).
 */
class CartTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelanggan = $this->makeUser();
    }

    private function addToCart(array $items, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->pelanggan)
            ->from(route('pelanggan.pesan.index'))
            ->post(route('pelanggan.cart.store'), ['items' => $items]);
    }

    public function test_tiga_layanan_sekaligus_dalam_satu_form_masuk_keranjang(): void
    {
        $a = $this->makeService(attributes: ['price' => 15000]);
        $b = $this->makeService(attributes: ['price' => 45000]);
        $c = $this->makeService(attributes: ['price' => 50000]);

        $this->addToCart([
            ['service_id' => $a->id, 'qty' => 2, 'item_type' => 'pakaian', 'notes' => 'Pisahkan putih'],
            ['service_id' => $b->id, 'qty' => 1, 'item_type' => 'sprei'],
            ['service_id' => $c->id, 'qty' => 1, 'item_type' => 'bedcover'],
        ])->assertRedirect(route('pelanggan.pesan.index'))->assertSessionHas('cart_highlight');

        $this->assertSame(3, $this->pelanggan->cartItems()->count());
        $this->assertDatabaseHas('cart_items', ['service_id' => $a->id, 'qty' => 2, 'notes' => 'Pisahkan putih']);
    }

    public function test_layanan_tidak_dicentang_qty_nol_diabaikan(): void
    {
        $a = $this->makeService();
        $b = $this->makeService();

        $this->addToCart([
            ['service_id' => $a->id, 'qty' => 1, 'item_type' => 'pakaian'],
            ['service_id' => $b->id, 'qty' => 0, 'item_type' => 'pakaian'],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$a->id], $this->pelanggan->cartItems()->pluck('service_id')->all());
    }

    public function test_semua_qty_nol_ditolak(): void
    {
        $a = $this->makeService();

        $this->addToCart([['service_id' => $a->id, 'qty' => 0, 'item_type' => 'pakaian']])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, CartItem::count());
    }

    public function test_satu_baris_tidak_valid_maka_tidak_ada_yang_tersimpan(): void
    {
        $ok = $this->makeService();
        $self = $this->makeService('Self Service');
        $nonaktif = $this->makeService(attributes: ['status' => 'tidak_tersedia']);

        $this->addToCart([
            ['service_id' => $ok->id, 'qty' => 1, 'item_type' => 'pakaian'],
            ['service_id' => $self->id, 'qty' => 1, 'item_type' => 'pakaian'],
            ['service_id' => $nonaktif->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ])->assertSessionHasErrors(['items.1.service_id', 'items.2.service_id']);

        $this->assertSame(0, CartItem::count());
    }

    public function test_jenis_cucian_dan_qty_divalidasi_per_baris(): void
    {
        $a = $this->makeService();

        $this->addToCart([
            ['service_id' => $a->id, 'qty' => 21, 'item_type' => 'karpet'],
        ])->assertSessionHasErrors(['items.0.qty', 'items.0.item_type']);
    }

    public function test_layanan_dan_jenis_sama_digabung_dan_dibatasi_maksimal(): void
    {
        $a = $this->makeService();

        $this->addToCart([['service_id' => $a->id, 'qty' => 5, 'item_type' => 'pakaian']]);
        $this->addToCart([['service_id' => $a->id, 'qty' => 3, 'item_type' => 'pakaian']]);

        $this->assertSame(1, $this->pelanggan->cartItems()->count());
        $this->assertSame(8, $this->pelanggan->cartItems()->first()->qty);

        // Jenis berbeda = baris berbeda, tetapi total per layanan tetap maks. 20 mesin
        $this->addToCart([['service_id' => $a->id, 'qty' => 13, 'item_type' => 'sprei']])
            ->assertSessionHasErrors('items.0.qty');
        $this->addToCart([['service_id' => $a->id, 'qty' => 12, 'item_type' => 'sprei']])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, (int) $this->pelanggan->cartItems()->sum('qty'));
    }

    public function test_jumlah_baris_keranjang_dibatasi(): void
    {
        config(['laundry.cart_max_lines' => 2]);
        $services = collect(range(1, 3))->map(fn () => $this->makeService());

        $this->addToCart($services->map(fn ($s) => ['service_id' => $s->id, 'qty' => 1, 'item_type' => 'pakaian'])->all())
            ->assertSessionHasErrors('items');

        $this->assertSame(0, CartItem::count());
    }

    public function test_ubah_qty_dan_hapus_item(): void
    {
        $a = $this->makeService();
        $this->addToCart([['service_id' => $a->id, 'qty' => 1, 'item_type' => 'pakaian']]);
        $item = $this->pelanggan->cartItems()->first();

        $this->actingAs($this->pelanggan)->patch(route('pelanggan.cart.update', $item->id), ['qty' => 4])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()->qty);

        $this->actingAs($this->pelanggan)->patch(route('pelanggan.cart.update', $item->id), ['qty' => 0])
            ->assertSessionHasErrors('qty');
        $this->assertSame(4, $item->fresh()->qty);

        $this->actingAs($this->pelanggan)->delete(route('pelanggan.cart.destroy', $item->id));
        $this->assertModelMissing($item);
    }

    public function test_pelanggan_tidak_bisa_mengubah_keranjang_orang_lain(): void
    {
        $a = $this->makeService();
        $this->addToCart([['service_id' => $a->id, 'qty' => 1, 'item_type' => 'pakaian']]);
        $item = $this->pelanggan->cartItems()->first();
        $lain = $this->makeUser();

        $this->actingAs($lain)->patch(route('pelanggan.cart.update', $item->id), ['qty' => 9])->assertNotFound();
        $this->actingAs($lain)->delete(route('pelanggan.cart.destroy', $item->id))->assertNotFound();

        $this->assertSame(1, $item->fresh()->qty);
    }

    public function test_hanya_pelanggan_yang_bisa_memakai_keranjang(): void
    {
        $a = $this->makeService();
        $items = [['service_id' => $a->id, 'qty' => 1, 'item_type' => 'pakaian']];

        $this->post(route('pelanggan.cart.store'), ['items' => $items])->assertRedirect(route('login'));
        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->post(route('pelanggan.cart.store'), ['items' => $items])->assertForbidden();
    }

    public function test_halaman_pesan_menampilkan_isi_keranjang_dan_menyorot_item_baru(): void
    {
        $a = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji', 'price' => 50000]);
        $b = $this->makeService(attributes: ['name' => 'Cuci Saja Uji', 'price' => 15000]);

        $this->addToCart([
            ['service_id' => $a->id, 'qty' => 2, 'item_type' => 'sprei', 'notes' => 'Wangi lavender'],
        ]);
        $b->update(['status' => 'tidak_tersedia']);
        $this->pelanggan->cartItems()->create(['service_id' => $b->id, 'qty' => 1, 'item_type' => 'pakaian']);

        $this->actingAs($this->pelanggan)
            ->withSession(['cart_highlight' => [$this->pelanggan->cartItems()->first()->id]])
            ->get(route('pelanggan.pesan.index'))
            ->assertOk()
            ->assertSeeInOrder(['Cuci Lipat Uji', 'Sprei / Selimut Tipis', 'Wangi lavender', 'Rp 100.000'])
            ->assertSee('BARU')
            ->assertSee('Layanan sedang tidak tersedia, tidak ikut dipesan.')
            ->assertSee('2 mesin')
            ->assertSee('Lanjut ke Detail Pesanan')
            // Perkiraan muatan hanya bantuan opsional: tidak pernah dikirim sebagai input form
            ->assertSee('Hitung dari perkiraan muatan (opsional)')
            ->assertDontSee('[weight]', false);
    }

    public function test_ringkasan_memakai_harga_terbaru_dan_memisahkan_layanan_nonaktif(): void
    {
        $a = $this->makeService(attributes: ['price' => 10000]);
        $b = $this->makeService(attributes: ['price' => 20000]);
        $this->addToCart([
            ['service_id' => $a->id, 'qty' => 2, 'item_type' => 'pakaian'],
            ['service_id' => $b->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]);

        $a->update(['price' => 12000]);
        $b->update(['status' => 'tidak_tersedia']);

        $summary = app(\App\Services\CartService::class)->summary($this->pelanggan);

        $this->assertEquals(24000, $summary['total']);
        $this->assertSame(2, $summary['machine_count']);
        $this->assertCount(1, $summary['unavailable']);
    }
}
