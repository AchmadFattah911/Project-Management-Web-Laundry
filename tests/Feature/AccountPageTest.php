<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Plan E: halaman Akun pelanggan gaya itemku (pengganti dashboard pelanggan).
 */
class AccountPageTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $budi;

    /** @var array<string, string> status => order_code */
    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->budi = $this->makeUser(attributes: ['name' => 'Budi Santoso', 'phone' => '081234567890']);
        $service = $this->makeService();

        // Alur status: pending → diproses → siap_diambil → selesai (sudah dilaundry & sudah diambil)
        foreach (['pending', 'diproses', 'siap_diambil', 'selesai'] as $i => $status) {
            $order = $this->makeOrder($this->budi, $service, ['status' => $status, 'order_code' => 'LC-' . strtoupper(substr($status, 0, 6))]);
            $order->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
            $this->codes[$status] = $order->order_code;
        }

        $this->makeOrder($this->makeUser(), $service, ['order_code' => 'LC-ORGLAIN']);
    }

    private function section(string $html, string $marker, string $end = '</section>'): string
    {
        $start = strpos($html, $marker);

        return substr($html, $start, strpos($html, $end, $start) - $start);
    }

    public function test_hanya_pelanggan_yang_bisa_membuka_halaman_akun(): void
    {
        $this->get('/akun')->assertRedirect(route('login'));
        $this->actingAs($this->makeUser(UserRole::ADMIN))->get('/akun')->assertForbidden();
        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->get('/akun')->assertForbidden();
        $this->actingAs($this->budi)->get('/akun')->assertOk();
    }

    public function test_dashboard_pelanggan_lama_diganti_halaman_akun(): void
    {
        $this->assertFalse(Route::has('pelanggan.dashboard'));
        $this->assertFalse(view()->exists('pelanggan.dashboard'));

        $this->actingAs($this->budi)->get('/pelanggan/dashboard')->assertRedirect('/akun');
        $this->actingAs($this->budi)->get('/dashboard')->assertRedirect(route('home'));
    }

    public function test_header_biru_dengan_ikon_kanan_atas_dan_profil_rata_kanan(): void
    {
        $header = $this->section($this->actingAs($this->budi)->get('/akun')->getContent(), '<header', '</header>');

        $this->assertStringContainsString('from-sky-600', $header);

        preg_match_all('/aria-label="(Notifikasi|Pesan|Pengaturan)"/', $header, $icons);
        $this->assertSame(['Notifikasi', 'Pesan', 'Pengaturan'], $icons[1]);

        $profile = substr($header, strpos($header, 'justify-end'));
        $name = strpos($profile, 'Budi Santoso');
        $pencil = strpos($profile, 'aria-label="Edit profil"');
        $avatar = strpos($profile, 'w-16 h-16 rounded-full');

        $this->assertTrue($name !== false && $pencil > $name && $avatar > $pencil, 'Urutan harus: nama, pensil, foto profil paling kanan.');
        $this->assertStringContainsString('BS', substr($profile, $avatar));
        $this->assertMatchesRegularExpression('/bg-amber-400[^>]*>3</', $header, 'Badge notifikasi = jumlah pesanan Di proses (termasuk siap diambil).');

        foreach (['saldo', 'token', 'kupon', 'koin'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, strip_tags($header));
        }
    }

    public function test_elemen_yang_diminta_dihapus_tidak_muncul(): void
    {
        $text = strip_tags($this->actingAs($this->budi)->get('/akun')->getContent());

        foreach (['DANA', 'OVO', 'Dompetku', 'Mulai Jualan', 'Kendala Pesanan', 'Pusat Bantuan', 'Daftar Komplain', 'Informasi & Komunitas', 'pembaruan'] as $removed) {
            $this->assertStringNotContainsString($removed, $text);
        }
    }

    public function test_riwayat_pemesanan_dengan_tiga_tab_status(): void
    {
        $riwayat = $this->section($this->actingAs($this->budi)->get('/akun')->getContent(), 'id="riwayat"');

        $this->assertStringContainsString('Riwayat Pemesanan', $riwayat);
        $this->assertGreaterThan(strpos($riwayat, 'Riwayat Pemesanan'), strpos($riwayat, 'Lihat Semua'));

        preg_match_all('/rounded-full text-xs font-bold border[^>]*>\s*<span>([^<]+)<\/span>\s*<span[^>]*>(\d+)</', $riwayat, $tabs);
        $this->assertSame(['Semua', 'Di proses', 'Selesai'], $tabs[1]);
        // Tidak ada pesanan yang terhitung di dua tab sekaligus: 3 + 1 = 4
        $this->assertSame(['4', '3', '1'], $tabs[2]);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<span>Semua</', $riwayat);

        foreach ($this->codes as $code) {
            $this->assertStringContainsString($code, $riwayat);
        }
        $this->assertStringNotContainsString('LC-ORGLAIN', $riwayat);
        $this->assertLessThan(strpos($riwayat, $this->codes['pending']), strpos($riwayat, $this->codes['selesai']), 'Pesanan terbaru di atas.');
    }

    public function test_tab_di_proses_dan_selesai_memfilter_dengan_benar(): void
    {
        $diproses = $this->section($this->actingAs($this->budi)->get('/akun?status=diproses')->getContent(), 'id="riwayat"');
        $this->assertStringContainsString($this->codes['pending'], $diproses);
        $this->assertStringContainsString($this->codes['diproses'], $diproses);
        $this->assertStringContainsString($this->codes['siap_diambil'], $diproses, 'Siap diambil = belum diambil, masih di tab Di proses.');
        $this->assertStringNotContainsString($this->codes['selesai'], $diproses);
        $this->assertStringContainsString('href="' . route('pelanggan.orders.index', ['status' => 'diproses']) . '"', $diproses);

        $selesai = $this->section($this->actingAs($this->budi)->get('/akun?status=selesai')->getContent(), 'id="riwayat"');
        $this->assertStringContainsString($this->codes['selesai'], $selesai);
        $this->assertStringNotContainsString($this->codes['siap_diambil'], $selesai);
        $this->assertStringNotContainsString($this->codes['pending'], $selesai);

        $this->actingAs($this->budi)->get('/akun?status=ngawur')
            ->assertSee($this->codes['pending'])
            ->assertSee($this->codes['selesai']);

        $this->actingAs($this->budi)->get(route('pelanggan.orders.index', ['status' => 'selesai']))
            ->assertSee($this->codes['selesai'])
            ->assertDontSee($this->codes['siap_diambil'])
            ->assertDontSee($this->codes['pending']);
    }

    public function test_halaman_akun_maksimal_lima_pesanan(): void
    {
        $service = $this->makeService();
        for ($i = 0; $i < 4; $i++) {
            $this->makeOrder($this->budi, $service);
        }

        $riwayat = $this->section($this->actingAs($this->budi)->get('/akun')->getContent(), 'id="riwayat"');

        $this->assertSame(5, preg_match_all('/href="[^"]*\/pelanggan\/orders\/\d+"/', $riwayat));
    }

    public function test_pelanggan_baru_melihat_pesan_kosong(): void
    {
        $this->actingAs($this->makeUser())->get('/akun')
            ->assertSee('Belum ada pesanan')
            ->assertSee('Pesan Layanan Sekarang');
    }

    public function test_accordion_tentang_kami_berisi_tiga_sub_menu(): void
    {
        $html = $this->actingAs($this->budi)->get('/akun')->getContent();
        $menu = $this->section($html, 'aria-controls="tentang-kami-menu"');

        $this->assertStringContainsString('x-collapse', $menu);
        preg_match_all('/<span>([^<]+)<\/span>\s*<svg[^>]*>\s*<path[^>]*d="M9 5l7 7-7 7"/', $menu, $items);
        $this->assertSame(['Tentang Laundry Coin', 'Hubungi Kami', 'Aturan Penggunaan'], $items[1]);
        $this->assertStringContainsString(route('tentang'), $menu);
        $this->assertStringContainsString(route('aturan'), $menu);
    }

    public function test_bottom_navigation_dengan_tab_akun_aktif(): void
    {
        $html = $this->actingAs($this->budi)->get('/akun')->getContent();
        $nav = $this->section($html, 'aria-label="Navigasi utama"', '</nav>');

        preg_match_all('/<span>([^<]+)<\/span>/', $nav, $items);
        $this->assertSame(['Beranda', 'Favorit', 'Transaksi', 'Bantuan', 'Akun'], $items[1]);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/aria-current="page"(?:(?!<\/a>).)*<span>Akun<\/span>/s', $nav);
        $this->assertStringContainsString('href="' . route('pelanggan.orders.index') . '"', $nav);
        $this->assertStringContainsString('fixed bottom-0', $html);
    }

    public function test_navbar_landing_menampilkan_profil_pelanggan_ke_halaman_akun(): void
    {
        $this->actingAs($this->budi)->get('/')
            ->assertSee('href="' . route('pelanggan.akun') . '"', false)
            ->assertSee('Budi Santoso')
            ->assertSee('Akun Saya');
    }

    public function test_tombol_keluar_berfungsi(): void
    {
        $this->actingAs($this->budi)->get('/akun')->assertSee('action="' . route('logout') . '"', false);
        $this->actingAs($this->budi)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
