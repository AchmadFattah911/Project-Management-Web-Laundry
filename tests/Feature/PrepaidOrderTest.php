<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Aturan bayar dulu: pesanan baru sah setelah lunas, dan pegawai tidak bisa mengatur status pembayaran.
 */
class PrepaidOrderTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
    }

    private function paidOrder(array $attributes = []): Order
    {
        $service = $this->makeService();
        $order = app(OrderService::class)->create(['service_id' => $service->id, 'machine_qty' => 2, 'customer_name' => 'Lunas'] + $attributes);

        $order->payments()->create([
            'amount'  => $order->total_price,
            'method'  => 'transfer',
            'gateway' => 'dummy',
            'status'  => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $order;
    }

    private function unpaidOrder(): Order
    {
        return app(OrderService::class)->create(['service_id' => $this->makeService()->id, 'machine_qty' => 1, 'customer_name' => 'Belum Bayar']);
    }

    public function test_pegawai_tidak_punya_jalur_mengubah_status_pembayaran(): void
    {
        $this->assertFalse(Route::has('pegawai.orders.payment'));

        $order = $this->unpaidOrder();

        $this->actingAs($this->pegawai)->post("/pegawai/orders/{$order->id}/payment", ['method' => 'cash'])
            ->assertNotFound();

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_update_pegawai_hanya_mengubah_status_pesanan(): void
    {
        $order = $this->paidOrder();
        $payment = $order->payments()->sole();

        $this->actingAs($this->pegawai)->put(route('pegawai.orders.update', $order), [
            'status'         => Order::STATUS_DIPROSES,
            'machine_qty'    => 9,
            'total_price'    => 1,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
        ])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::STATUS_DIPROSES, $order->status);
        $this->assertSame(2, $order->machine_qty);
        $this->assertEquals(30000, (float) $order->total_price);
        $this->assertSame(['paid', 'transfer'], [$payment->fresh()->status, $payment->fresh()->method]);
    }

    public function test_jumlah_mesin_terkunci_setelah_lunas_untuk_siapa_pun(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->makeUser(UserRole::ADMIN))->put(route('admin.orders.update', $order), ['machine_qty' => 5])
            ->assertSessionHas('error');

        $this->assertSame(2, $order->fresh()->machine_qty);
    }

    public function test_pesanan_belum_dibayar_tidak_tampil_di_daftar_dan_antrean_pegawai(): void
    {
        $paid = $this->paidOrder();
        $unpaid = $this->unpaidOrder();

        $this->actingAs($this->pegawai)->get(route('pegawai.orders.index'))
            ->assertSee($paid->order_code)
            ->assertDontSee($unpaid->order_code)
            ->assertDontSee('Menunggu Pembayaran')
            ->assertDontSee('Konfirmasi Lunas');

        $response = $this->actingAs($this->pegawai)->get(route('pegawai.dashboard'))
            ->assertSee($paid->order_code)
            ->assertDontSee($unpaid->order_code);

        $this->assertSame(1, $response->viewData('stats')['antrean_cuci']);
    }

    public function test_pesanan_belum_dibayar_tidak_bisa_dikerjakan(): void
    {
        $order = $this->unpaidOrder();
        $process = OrderProcess::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($this->pegawai)->put(route('pegawai.orders.update', $order), ['status' => Order::STATUS_DIPROSES])
            ->assertSessionHas('error');
        $this->actingAs($this->pegawai)->put(route('pegawai.orders.process', $process), ['status' => 'selesai'])
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame('pending', $process->fresh()->status);
    }

    public function test_pesanan_kasir_wajib_memilih_metode_dan_selalu_lunas(): void
    {
        $service = $this->makeService();
        $data = ['service_id' => $service->id, 'machine_qty' => 1, 'customer_name' => 'Kasir'];

        $this->actingAs($this->pegawai)->post(route('pegawai.orders.store'), $data)
            ->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());

        // is_paid dari form lama tidak lagi berpengaruh
        $this->actingAs($this->pegawai)->post(route('pegawai.orders.store'), $data + ['payment_method' => 'cash', 'cash_received' => 15000, 'submission_token' => (string) Str::uuid(), 'is_paid' => '0'])
            ->assertSessionHas('success');

        $this->assertTrue(Order::sole()->isPaid());
    }

    public function test_pesanan_kasir_batal_seluruhnya_jika_pelunasan_gagal(): void
    {
        $service = $this->makeService();

        try {
            app(OrderService::class)->createWalkIn([
                'service_id' => $service->id, 'machine_qty' => 1, 'customer_name' => 'Gagal',
                'payment_method' => 'cash', 'cash_received' => 1, 'submission_token' => (string) Str::uuid(),
            ], $this->pegawai);
            $this->fail('Uang kurang harus ditolak');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, Order::count());
        }
    }

    public function test_admin_tetap_bisa_melunasi_saat_notifikasi_gateway_hilang(): void
    {
        $order = $this->unpaidOrder();

        $this->actingAs($this->makeUser(UserRole::ADMIN))->post(route('admin.orders.payment', $order), ['method' => 'transfer'])
            ->assertSessionHas('success');

        $this->assertTrue($order->fresh()->isPaid());
    }
}
