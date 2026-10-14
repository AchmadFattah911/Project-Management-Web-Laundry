<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Plan C: isi landing page sesuai sistem (tanpa koin/token, pembayaran cashless, 4 langkah cara kerja).
 */
class LandingContentTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    /** Teks yang benar-benar terlihat pengunjung (tanpa script, komentar, dan tag HTML). */
    private function visibleText(string $html): string
    {
        $html = preg_replace(['/<script\b.*?<\/script>/is', '/<style\b.*?<\/style>/is', '/<!--.*?-->/s'], ' ', $html);

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="' . $id . '"');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public function test_landing_page_tidak_lagi_memakai_sistem_koin_atau_token(): void
    {
        $this->makeService('Self Service');
        $this->makeService();

        $text = $this->visibleText($this->get('/')->assertOk()->getContent());
        // Satu-satunya kalimat yang boleh menyebut koin/token adalah penegasan di FAQ
        $text = str_replace(['Laundry Coin tidak menggunakan koin atau token.', 'Laundry Coin'], '', $text);

        foreach (['koin', 'coin', 'token', 'dispenser', 'setrika'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, "Masih ada kata '{$word}' di landing page.");
        }

        $this->assertDoesNotMatchRegularExpression('/melayani antar-jemput|Pemesanan Antar-Jemput/i', $text);
    }

    public function test_cara_kerja_berisi_4_langkah_sesuai_urutan(): void
    {
        $text = $this->visibleText($this->section($this->get('/')->getContent(), 'cara-kerja'));

        $steps = [
            'Pilih Layanan & Masukkan Pakaian',
            'Pembayaran Online Cashless',
            'Menunggu Estimasi Penyelesaian Laundry',
            'Pesanan Selesai, Pakaian Kering Siap Dipakai',
        ];

        $lastPosition = -1;
        foreach ($steps as $step) {
            $position = strpos($text, $step);
            $this->assertNotFalse($position, "Langkah '{$step}' tidak ditemukan.");
            $this->assertGreaterThan($lastPosition, $position, "Urutan langkah '{$step}' salah.");
            $lastPosition = $position;
        }

        $this->assertStringContainsString('Pemesanan Online', $text);
        $this->assertStringContainsString('Langsung di Tempat (Self Laundry)', $text);
        $this->assertStringContainsString('cuci → kering → lipat', $text);
    }

    public function test_metode_pembayaran_di_cara_kerja_sama_dengan_config_dan_valid_di_database(): void
    {
        $text = $this->visibleText($this->section($this->get('/')->getContent(), 'cara-kerja'));
        $methodEnum = DB::selectOne("SHOW COLUMNS FROM payments WHERE Field = 'method'")->Type;

        $methods = config('laundry.online_payment_methods');
        $this->assertNotEmpty($methods);

        foreach ($methods as $key => $method) {
            $this->assertStringContainsString($method['label'], $text);
            $this->assertStringContainsString("'{$key}'", $methodEnum, "Metode '{$key}' tidak valid untuk kolom payments.method.");
        }
    }

    public function test_link_lacak_pesanan_di_cara_kerja_mengarah_ke_section_yang_ada(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('href="#lacak"', $this->section($html, 'cara-kerja'));
        $this->assertStringContainsString('id="lacak"', $html);
    }

    public function test_halaman_tentang_dan_aturan_penggunaan_bisa_dibuka(): void
    {
        $this->get(route('tentang'))->assertOk()->assertSee('Tentang Laundry Coin');
        $this->get(route('aturan'))->assertOk()
            ->assertSee('hanya bisa digunakan langsung di outlet')
            ->assertSee('cashless');
    }
}
