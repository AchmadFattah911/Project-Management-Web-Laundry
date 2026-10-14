<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Section Layanan di landing page & alur masuk ke halaman pemesanan (/pesan).
 */
class LandingOrderTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    /** Potongan HTML grup Self Laundry di section Layanan. */
    private function selfServiceBlock(string $html): string
    {
        $start = strpos($html, 'GRUP 1');

        return substr($html, $start, strpos($html, 'GRUP 2') - $start);
    }

    public function test_kartu_self_laundry_tanpa_tombol_dan_berlabel_khusus_di_tempat(): void
    {
        $this->makeService('Self Service', ['name' => 'Mesin Cuci A']);
        $this->makeService('Self Service', ['name' => 'Mesin Cuci B']);

        $block = $this->selfServiceBlock($this->get('/')->assertOk()->getContent());

        $this->assertSame(2, substr_count($block, 'Menu Khusus Langsung Ditempat'));
        $this->assertStringNotContainsString('<button', $block);
        $this->assertStringNotContainsString('<a ', $block);
        $this->assertStringNotContainsStringIgnoringCase('koin', strip_tags($block));
    }

    public function test_tamu_klik_pesan_sekarang_login_lalu_masuk_halaman_pemesanan(): void
    {
        $service = $this->makeService();
        $pelanggan = $this->makeUser();

        $this->get('/')->assertSee(route('pelanggan.orders.start', $service), false);

        $this->get(route('pelanggan.orders.start', $service))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $pelanggan->email, 'password' => 'password'])
            ->assertRedirect(route('pelanggan.orders.start', $service));

        $this->get(route('pelanggan.orders.start', $service))
            ->assertRedirect(route('pelanggan.pesan.index', ['layanan' => $service->slug]));

        // Kartu layanan dari tombol "Pesan Sekarang" langsung tercentang
        $html = $this->get(route('pelanggan.pesan.index', ['layanan' => $service->slug]))
            ->assertOk()
            ->assertSee('Cara Memesan di Halaman Ini')
            ->getContent();

        $this->assertSame(true, $this->initialSelection($html)[$service->id]['checked']);
    }

    public function test_pelanggan_di_landing_diarahkan_ke_halaman_pemesanan_bukan_modal(): void
    {
        $service = $this->makeService();
        $pelanggan = $this->makeUser();

        $this->actingAs($pelanggan)->get('/')
            ->assertSee(route('pelanggan.pesan.index', ['layanan' => $service->slug]), false)
            ->assertDontSee('open-order', false);
    }

    public function test_pelanggan_tidak_bisa_memesan_self_service_online(): void
    {
        $self = $this->makeService('Self Service');
        $pelanggan = $this->makeUser();

        $this->actingAs($pelanggan)->get(route('pelanggan.orders.start', $self))
            ->assertRedirect(route('home') . '#layanan')
            ->assertSessionHas('error');

        $this->actingAs($pelanggan)->post(route('pelanggan.cart.store'), [
            'items' => [['service_id' => $self->id, 'qty' => 1, 'item_type' => 'pakaian']],
        ])->assertSessionHasErrors('items.0.service_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_pilihan_layanan_pelanggan_hanya_non_self_service(): void
    {
        $self = $this->makeService('Self Service', ['name' => 'Mesin Self Rahasia']);
        $nonSelf = $this->makeService('Non-Self Service', ['name' => 'Cuci Lipat Online']);
        $pelanggan = $this->makeUser();

        $this->actingAs($pelanggan)->get(route('pelanggan.pesan.index'))
            ->assertSee($nonSelf->name)
            ->assertDontSee($self->name);
    }

    public function test_staf_melihat_info_pemesanan_khusus_pelanggan_dan_tetap_bisa_walk_in_self_service(): void
    {
        $self = $this->makeService('Self Service');
        $this->makeService();
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->get('/')
            ->assertSee('Pemesanan online khusus akun pelanggan');

        $this->actingAs($admin)->get(route('pelanggan.pesan.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'service_id'    => $self->id,
            'machine_qty'   => 1,
            'customer_name' => 'Walk In',
        ]);

        $this->assertDatabaseHas('orders', ['service_id' => $self->id, 'customer_name' => 'Walk In']);
    }

    public function test_validasi_gagal_kartu_tetap_tercentang_dengan_pesan_error_di_kartunya(): void
    {
        $service = $this->makeService();
        $pelanggan = $this->makeUser();

        // Catatan: jangan pakai assertSessionHasErrors() di sini, karena assertion itu ikut
        // mengonsumsi flash error sehingga request berikutnya tidak lagi melihatnya.
        $this->actingAs($pelanggan)->from(route('pelanggan.pesan.index'))->post(route('pelanggan.cart.store'), [
            'items' => [$service->id => ['service_id' => $service->id, 'qty' => 99, 'item_type' => 'pakaian', 'notes' => 'Wangi']],
        ])->assertRedirect(route('pelanggan.pesan.index'));

        $html = $this->actingAs($pelanggan)->get(route('pelanggan.pesan.index'))
            ->assertSee('Jumlah mesin maksimal 20 unit.')
            ->getContent();

        $selection = $this->initialSelection($html)[$service->id];
        $this->assertTrue($selection['checked']);
        $this->assertSame('Wangi', $selection['notes']);
        $this->assertSame(0, CartItem::count());
    }

    /** Status awal kartu layanan yang dirender ke x-data form pemesanan (sel: {...}). */
    private function initialSelection(string $html): array
    {
        // Js::from() menghasilkan JSON.parse('<json yang di-encode ulang>')
        preg_match("/sel: JSON\.parse\('(.*?)'\),/s", $html, $json);

        return json_decode(json_decode('"' . ($json[1] ?? '') . '"'), true) ?? [];
    }
}
