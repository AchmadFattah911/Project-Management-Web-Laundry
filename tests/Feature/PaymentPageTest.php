<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 6: tampilan halaman pembayaran untuk setiap keadaan tagihan.
 */
class PaymentPageTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    private Checkout $checkout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelanggan = $this->makeUser();
        $service = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji', 'price' => 50000]);

        $this->actingAs($this->pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $service->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]]);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), [
            'contact_phone'    => '081234567890',
            'drop_off_date'    => now()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ]);

        $this->checkout = Checkout::sole();
    }

    private function page()
    {
        return $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))->assertOk();
    }

    private function pay(): Payment
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.pay', $this->checkout), [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method'  => 'transfer',
        ]);

        return Payment::latest('id')->firstOrFail();
    }

    private function notify(Payment $payment, string $status): void
    {
        $gateway = app(DummyGateway::class);
        $n = $gateway->buildNotification($payment, $status);
        app(\App\Services\PaymentService::class)->handleNotification('dummy', $n['body'], $n['headers']);
    }

    public function test_belum_ada_tagihan_menampilkan_form_bayar_dan_tutorial(): void
    {
        $this->page()
            ->assertSee('Selesaikan Pembayaran')
            ->assertSee('Bayar Sekarang')
            ->assertSee('name="payment_method"', false)
            ->assertSee('Cara Membayar')
            ->assertSee('tidak akan ditagih dua kali')
            ->assertSee('Cuci Lipat Uji')
            ->assertSee('Rp 50.000');
    }

    public function test_tagihan_aktif_menampilkan_countdown_tautan_gateway_dan_ganti_metode(): void
    {
        $payment = $this->pay();

        $this->page()
            ->assertSee('Menunggu Pembayaran')
            ->assertSee('Transfer Bank')
            ->assertSee($payment->checkout_url, false)
            ->assertSee('paymentWatcher', false)
            // URL status ada di dalam @js(), sehingga "/" ditulis sebagai "\/"
            ->assertSee(str_replace('/', '\/', route('pelanggan.pembayaran.status', $this->checkout)), false)
            ->assertSee('Ganti metode pembayaran')
            ->assertSee('Buat Tagihan Baru');
    }

    public function test_tagihan_gagal_menampilkan_bayar_lagi(): void
    {
        $this->notify($this->pay(), 'failed');

        $this->page()
            ->assertSee('Pembayaran sebelumnya gagal. Tidak ada dana yang terpotong')
            ->assertSee('Bayar Lagi')
            ->assertDontSee('Lanjutkan ke Halaman Pembayaran');
    }

    public function test_tagihan_lewat_batas_waktu_dianggap_kadaluarsa(): void
    {
        $this->pay()->update(['expires_at' => now()->subMinute()]);

        $this->page()
            ->assertSee('Tagihan sebelumnya sudah kadaluarsa')
            ->assertSee('Bayar Lagi');
    }

    public function test_transaksi_lunas_dialihkan_ke_halaman_selesai(): void
    {
        $this->notify($this->pay(), 'paid');

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertRedirect(route('pelanggan.pembayaran.selesai', $this->checkout));
    }

    // ── Tahap 7: halaman setelah pembayaran ─────────────────────────

    public function test_halaman_selesai_menampilkan_riwayat_dan_panduan_riwayat_lengkap(): void
    {
        $older = $this->makeOrder($this->pelanggan, $this->makeService(attributes: ['name' => 'Cuci Kering Lama']), ['order_code' => 'LC-LAMA01']);
        $other = $this->makeOrder($this->makeUser(), $this->makeService(attributes: ['name' => 'Milik Orang Lain']));
        $this->notify($this->pay(), 'paid');
        $order = $this->checkout->orders()->sole();

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.selesai', $this->checkout))
            ->assertOk()
            ->assertSee('Pembayaran Berhasil!')
            ->assertSee($this->checkout->code)
            ->assertSee('Transfer Bank')
            ->assertSee('Langkah Berikutnya')
            ->assertSee('Riwayat Pemesanan Layanan')
            ->assertSee($order->order_code)
            ->assertSeeInOrder(['Cuci', 'Kering', 'Lipat'])
            ->assertSee('Pesanan Anda Sebelumnya')
            ->assertSee($older->order_code)
            ->assertDontSee($other->order_code)
            ->assertSee('Jika ingin melihat semua riwayat pemesanan Anda, silakan kembali ke halaman utama dan klik', false)
            ->assertSee('profil pengguna')
            ->assertSee('Kembali ke Beranda');
    }

    public function test_halaman_selesai_untuk_transaksi_belum_lunas_dikembalikan_ke_pembayaran(): void
    {
        $this->pay();

        $this->actingAs($this->pelanggan)->get(route('pelanggan.pembayaran.selesai', $this->checkout))
            ->assertRedirect(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertSessionHas('status');
    }

    public function test_halaman_selesai_hanya_untuk_pemilik(): void
    {
        $this->notify($this->pay(), 'paid');

        $this->actingAs($this->makeUser())->get(route('pelanggan.pembayaran.selesai', $this->checkout))->assertForbidden();
    }

    public function test_simulasi_bayar_berhasil_langsung_ke_halaman_selesai(): void
    {
        // Gagal → kembali ke halaman pembayaran untuk bayar lagi
        $this->actingAs($this->pelanggan)->post(route('payments.dummy.simulate', $this->pay()->reference), ['result' => 'failed'])
            ->assertRedirect(route('pelanggan.pembayaran.show', $this->checkout))
            ->assertSessionHas('error');

        // Berhasil → langsung ke halaman setelah pembayaran
        $this->actingAs($this->pelanggan)->post(route('payments.dummy.simulate', $this->pay()->reference), ['result' => 'paid'])
            ->assertRedirect(route('pelanggan.pembayaran.selesai', $this->checkout));
    }

    public function test_endpoint_status_untuk_polling(): void
    {
        $payment = $this->pay();

        $this->actingAs($this->pelanggan)->getJson(route('pelanggan.pembayaran.status', $this->checkout))
            ->assertOk()
            ->assertExactJson(['paid' => false, 'status' => 'menunggu_pembayaran', 'payment_status' => 'pending']);

        $this->notify($payment, 'paid');

        $this->actingAs($this->pelanggan)->getJson(route('pelanggan.pembayaran.status', $this->checkout))
            ->assertJson(['paid' => true, 'status' => 'lunas', 'payment_status' => 'paid']);

        $this->actingAs($this->makeUser())->getJson(route('pelanggan.pembayaran.status', $this->checkout))->assertForbidden();
    }
}
