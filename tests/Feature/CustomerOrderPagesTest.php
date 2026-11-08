<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 6: halaman pesanan pelanggan (/akun dan /pelanggan/orders) dengan tab Belum Bayar / Diproses /
 * Selesai / Dibatalkan, 1 kartu = 1 transaksi, dan detail transaksi dengan timeline.
 */
class CustomerOrderPagesTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.expiry_minutes' => 60]);
        $this->freezeSecond();
        $this->pelanggan = $this->makeUser();
    }

    /**
     * Transaksi langsung di database (tanpa log), dengan $orders pesanan berstatus $orderStatus.
     */
    private function checkout(string $status, int $orders = 2, string $orderStatus = Order::STATUS_PENDING, ?User $user = null, array $attributes = []): Checkout
    {
        $user ??= $this->pelanggan;
        $service = $this->makeService(attributes: ['name' => 'Cuci Uji ' . Str::random(5), 'price' => 10000]);

        $checkout = Checkout::create($attributes + [
            'code'             => 'TRX-' . strtoupper(Str::random(8)),
            'user_id'          => $user->id,
            'total_amount'     => 10000 * $orders,
            'status'           => $status,
            'payment_deadline' => now()->addHour(),
            'paid_at'          => $status === Checkout::STATUS_PAID ? now() : null,
            'contact_phone'    => '081234567890',
            'drop_off_date'    => now()->addDay()->toDateString(),
            'submission_token' => (string) Str::uuid(),
        ]);

        foreach (range(1, $orders) as $i) {
            $this->makeOrder($user, $service, ['checkout_id' => $checkout->id, 'status' => $orderStatus, 'machine_qty' => 1, 'unit_price' => 10000, 'total_price' => 10000]);
        }

        if ($status === Checkout::STATUS_PAID) {
            $checkout->payments()->create(['amount' => $checkout->total_amount, 'method' => 'transfer', 'gateway' => 'midtrans', 'reference' => $checkout->code . '-P1', 'status' => Payment::STATUS_PAID, 'paid_at' => now()]);
        }

        return $checkout->fresh();
    }

    private function cardCodes(string $html): array
    {
        preg_match_all('/data-card="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function test_tab_mengelompokkan_transaksi_dan_satu_kartu_per_transaksi(): void
    {
        $awaiting = $this->checkout(Checkout::STATUS_AWAITING);
        $processing = $this->checkout(Checkout::STATUS_PAID, orderStatus: Order::STATUS_DIPROSES);
        $done = $this->checkout(Checkout::STATUS_PAID, orderStatus: Order::STATUS_SELESAI);
        $expired = $this->checkout(Checkout::STATUS_EXPIRED);
        $failed = $this->checkout(Checkout::STATUS_FAILED);
        $cancelled = $this->checkout(Checkout::STATUS_CANCELLED);
        $walkIn = $this->makeOrder($this->pelanggan, $this->makeService(), ['status' => Order::STATUS_DIPROSES, 'order_code' => 'LC-WALKIN1']);

        // Transaksi lunas dengan 1 pesanan selesai + 1 belum → masih Diproses
        $mixed = $this->checkout(Checkout::STATUS_PAID, orderStatus: Order::STATUS_SELESAI);
        $mixed->orders()->first()->update(['status' => Order::STATUS_SIAP_DIAMBIL]);

        $expected = [
            'belum_bayar' => [$awaiting->code],
            'diproses'    => [$processing->code, $mixed->code, 'LC-WALKIN1'],
            'selesai'     => [$done->code],
            'dibatalkan'  => [$expired->code, $failed->code, $cancelled->code],
        ];

        foreach ($expected as $tab => $codes) {
            foreach (['pelanggan.orders.index', 'pelanggan.akun'] as $route) {
                $html = $this->actingAs($this->pelanggan)->get(route($route, ['status' => $tab]))->assertOk()->getContent();
                $this->assertEqualsCanonicalizing($codes, $this->cardCodes($html), "{$route} tab {$tab}");
            }
        }

        // Semua: 8 kartu untuk 8 transaksi/pesanan (bukan 15 baris order)
        $all = $this->cardCodes($this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index'))->getContent());
        $this->assertCount(8, $all);
        $this->assertSame(count($all), count(array_unique($all)));
        $this->assertNotNull($walkIn);
    }

    public function test_hanya_transaksi_milik_pelanggan_yang_tampil(): void
    {
        $mine = $this->checkout(Checkout::STATUS_AWAITING);
        $other = $this->checkout(Checkout::STATUS_AWAITING, user: $this->makeUser());

        $html = $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index'))->getContent();

        $this->assertSame([$mine->code], $this->cardCodes($html));
        $this->assertStringNotContainsString($other->code, $html);

        $this->actingAs($this->pelanggan)->get(route('pelanggan.transaksi.show', $other))->assertNotFound();
        $this->actingAs($this->pelanggan)->get(route('pelanggan.transaksi.show', $mine))->assertOk();
    }

    public function test_daftar_pesanan_dipaginasi(): void
    {
        foreach (range(1, 12) as $i) {
            $this->travel(1)->minutes();
            $this->checkout(Checkout::STATUS_CANCELLED, orders: 1);
        }

        $page1 = $this->cardCodes($this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index', ['status' => 'dibatalkan']))->getContent());
        $page2 = $this->cardCodes($this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index', ['status' => 'dibatalkan', 'page' => 2]))->getContent());

        $this->assertCount(10, $page1);
        $this->assertCount(2, $page2);
        $this->assertEmpty(array_intersect($page1, $page2));
        $this->assertSame(Checkout::latest('id')->value('code'), $page1[0], 'Terbaru di atas.');

        // Halaman Akun hanya pratinjau 5 kartu
        $this->assertCount(5, $this->cardCodes($this->actingAs($this->pelanggan)->get(route('pelanggan.akun', ['status' => 'dibatalkan']))->getContent()));
    }

    public function test_kartu_belum_bayar_berisi_hitung_mundur_bayar_batal_dan_info_va(): void
    {
        $checkout = $this->checkout(Checkout::STATUS_AWAITING, attributes: ['pending_reason' => 'menunggu_transfer']);
        $checkout->payments()->create([
            'amount' => $checkout->total_amount, 'method' => 'transfer', 'gateway' => 'midtrans', 'reference' => $checkout->code . '-P1',
            'status' => Payment::STATUS_PENDING, 'snap_token' => 'tok', 'payment_type' => 'bank_transfer',
            'meta' => ['midtrans' => ['instruction' => ['bank' => 'BCA', 'number' => '70012345678']]],
        ]);

        $html = $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index', ['status' => 'belum_bayar']))->getContent();

        $this->assertStringContainsString('deadline: ' . $checkout->paymentDeadline()->getTimestampMs(), $html);
        $this->assertStringContainsString("expired ? 'Kedaluwarsa' : label", $html, 'Hitung mundur berganti Kedaluwarsa tanpa reload.');
        $this->assertStringContainsString('href="' . route('pelanggan.pembayaran.show', $checkout) . '"', $html);
        $this->assertStringContainsString('action="' . route('pelanggan.pembayaran.cancel', $checkout) . '"', $html);
        $this->assertStringContainsString('Bayar Sekarang', $html);
        $this->assertStringContainsString('70012345678', $html);
        $this->assertStringContainsString('Virtual Account', $html);
        $this->assertStringContainsString('Selesaikan transfer sebelum batas waktu', $html, 'Keterangan dari pending_reason menunggu_transfer.');
    }

    public function test_kartu_dibatalkan_punya_tombol_pesan_lagi(): void
    {
        $checkout = $this->checkout(Checkout::STATUS_EXPIRED);

        $html = $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index', ['status' => 'dibatalkan']))->getContent();

        $this->assertStringContainsString('action="' . route('pelanggan.pembayaran.reorder', $checkout) . '"', $html);
        $this->assertStringContainsString('Kedaluwarsa', $html);
        $this->assertStringNotContainsString('action="' . route('pelanggan.pembayaran.cancel', $checkout) . '"', $html);
    }

    public function test_timeline_dari_log_status(): void
    {
        $checkout = $this->checkout(Checkout::STATUS_AWAITING, orders: 1);
        $machine = app(OrderStateMachine::class);
        $machine->recordCreated($checkout, $this->pelanggan);

        $this->travel(5)->minutes();
        $machine->transitionCheckout($checkout, Checkout::STATUS_PAID, OrderStateMachine::ACTOR_WEBHOOK, note: 'Lunas via Midtrans (TRX-INTERNAL-P1)');
        $checkout->payments()->create(['amount' => $checkout->total_amount, 'method' => 'transfer', 'gateway' => 'midtrans', 'reference' => $checkout->code . '-P1', 'status' => Payment::STATUS_PAID, 'paid_at' => now()]);

        $this->travel(5)->minutes();
        $order = $checkout->orders()->first();
        $machine->transitionOrder($order, Order::STATUS_DIPROSES, $this->makeUser(\App\Enums\UserRole::PEGAWAI));

        $html = $this->actingAs($this->pelanggan)->get(route('pelanggan.transaksi.show', $checkout))->assertOk()->getContent();

        preg_match_all('/data-timeline-row>.*?<p[^>]*>([^<]+)<\/p>/s', $html, $rows);
        $this->assertSame(['Pesanan dibuat', 'Pembayaran diterima', $order->serviceName() . ' sedang diproses'], array_map('trim', $rows[1]));
        $this->assertStringNotContainsString('TRX-INTERNAL-P1', $html, 'Catatan internal tidak tampil ke pelanggan.');
    }

    public function test_timeline_data_lama_tanpa_log_memakai_created_at(): void
    {
        $checkout = $this->checkout(Checkout::STATUS_PAID, orders: 1);
        $checkout->forceFill(['created_at' => now()->subDays(3)->setTime(9, 15)])->save();
        $this->assertSame(0, OrderStatusLog::count());

        $html = $this->actingAs($this->pelanggan)->get(route('pelanggan.transaksi.show', $checkout))->assertOk()->getContent();

        preg_match_all('/data-timeline-row>.*?<p[^>]*>([^<]+)<\/p>\s*<time[^>]*datetime="([^"]+)"/s', $html, $rows);
        $this->assertSame('Pesanan dibuat', trim($rows[1][0]));
        $this->assertSame($checkout->created_at->toIso8601String(), $rows[2][0]);
        $this->assertSame('Pembayaran diterima', trim($rows[1][1]));

        // Pesanan walk-in lama tanpa log: detail pesanan juga memakai created_at
        $walkIn = $this->makeOrder($this->pelanggan, $this->makeService(), ['status' => Order::STATUS_SELESAI]);
        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.show', $walkIn))
            ->assertOk()
            ->assertSee('Pesanan dibuat')
            ->assertSee($walkIn->created_at->toIso8601String(), false);
    }

    public function test_tab_tidak_dikenal_kembali_ke_semua(): void
    {
        $this->checkout(Checkout::STATUS_AWAITING);

        $this->actingAs($this->pelanggan)->get(route('pelanggan.orders.index', ['status' => 'ngawur']))
            ->assertOk()
            ->assertViewHas('tab', 'semua');
    }
}
