<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\RunsParallelWorkers;
use Tests\TestCase;

/**
 * Tes wajib #1: form checkout yang sama (submission_token sama) dikirim 3x BENAR-BENAR paralel
 * (3 proses PHP terpisah) → 1 transaksi, order tidak ganda, 3 respons identik (semua diarahkan ke
 * transaksi yang sama). Sesuai keputusan: submit ganda = redirect + flash, bukan halaman 409/422.
 */
class CheckoutConcurrencyTest extends TestCase
{
    use DatabaseTruncation, CreatesLaundryData, RunsParallelWorkers;

    public function test_checkout_dikirim_tiga_kali_paralel_hanya_satu_transaksi(): void
    {
        $pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat Paralel', 'price' => 35000]);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja Paralel', 'price' => 15000]);

        $this->actingAs($pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $lipat->id, 'qty' => 2, 'item_type' => 'sprei'],
            ['service_id' => $saja->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasNoErrors();

        $form = tempnam(sys_get_temp_dir(), 'checkout');
        file_put_contents($form, json_encode([
            'cart_items'       => $pelanggan->cartItems()->pluck('id')->all(),
            'drop_off_date'    => now()->addDay()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
            // Harga palsu dari client ikut dikirim dan harus diabaikan
            'total_amount'     => 1,
        ]));

        try {
            $responses = $this->runParallelWorkers('checkout_worker.php', array_fill(0, 3, [(string) $pelanggan->id, $form]));
        } finally {
            @unlink($form);
        }

        $checkout = Checkout::sole();

        // 3 respons identik: semuanya diarahkan ke halaman bayar transaksi yang sama
        $this->assertSame([302, 302, 302], $responses->pluck('http')->all());
        $this->assertSame(array_fill(0, 3, route('pelanggan.pembayaran.show', $checkout)), $responses->pluck('location')->all());

        $this->assertSame(2, Order::where('checkout_id', $checkout->id)->count());
        $this->assertSame(2, Order::count());
        $this->assertSame(85000, $checkout->totalRupiah());
        $this->assertSame(1, IdempotencyKey::where('scope', 'checkout')->count());
        $this->assertSame(1, OrderStatusLog::whereNotNull('checkout_id')->whereNull('from_status')->count());
        // Tagihan baru dibuat saat pelanggan menekan Bayar Sekarang, bukan saat checkout
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, $pelanggan->cartItems()->count());
    }
}
