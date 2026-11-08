<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use App\Services\OrderService;
use App\Services\OrderStateMachine;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 3 pembayaran: semua perubahan status lewat OrderStateMachine dan tercatat di order_status_logs.
 */
class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pelanggan;

    private User $pegawai;

    private User $admin;

    private OrderStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
        $this->machine = app(OrderStateMachine::class);
    }

    private function paidOrder(string $status = Order::STATUS_PENDING): Order
    {
        $order = app(OrderService::class)->create(['service_id' => $this->makeService()->id, 'customer_name' => 'Lunas']);
        $order->payments()->create([
            'amount'  => $order->total_price,
            'method'  => 'cash',
            'gateway' => Payment::GATEWAY_MANUAL,
            'status'  => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        // Status awal disiapkan langsung (bukan bagian yang diuji)
        $order->forceFill(['status' => $status])->save();

        return $order;
    }

    private function updateAs(User $user, Order $order, array $data)
    {
        $route = $user->isAdmin() ? 'admin.orders.update' : 'pegawai.orders.update';

        return $this->actingAs($user)->from(route($user->isAdmin() ? 'admin.orders.index' : 'pegawai.orders.index'))
            ->put(route($route, $order), $data);
    }

    private function checkoutFromCart(): Checkout
    {
        $service = $this->makeService(attributes: ['price' => 35000]);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $service->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]]);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.checkout.store'), [
            'cart_items'       => $this->pelanggan->cartItems()->pluck('id')->all(),
            'drop_off_date'    => now()->addDay()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Checkout::sole();
    }

    private function payViaWebhook(Checkout $checkout): array
    {
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.pay', $checkout), ['payment_method' => 'transfer']);
        $payment = $checkout->payments()->sole();

        return app(DummyGateway::class)->buildNotification($payment, 'paid');
    }

    private function logsFor(Checkout|Order $subject)
    {
        return $subject->statusLogs()->get();
    }

    // ── Pesanan: hanya maju ─────────────────────────────────────

    public function test_pegawai_memajukan_status_dan_tercatat_di_log(): void
    {
        $order = $this->paidOrder();

        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_DIPROSES])->assertSessionHas('success');

        $this->assertSame(Order::STATUS_DIPROSES, $order->fresh()->status);
        $log = $this->logsFor($order)->last();
        $this->assertSame([Order::STATUS_PENDING, Order::STATUS_DIPROSES], [$log->from_status, $log->to_status]);
        $this->assertSame(OrderStateMachine::ACTOR_PEGAWAI, $log->actor_type);
        $this->assertSame($this->pegawai->id, $log->actor_id);
    }

    public function test_status_boleh_melompat_maju(): void
    {
        $order = $this->paidOrder();

        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_SELESAI])->assertSessionHas('success');

        $this->assertSame(Order::STATUS_SELESAI, $order->fresh()->status);
    }

    public function test_pegawai_tidak_bisa_memundurkan_status(): void
    {
        $order = $this->paidOrder(Order::STATUS_SIAP_DIAMBIL);
        $logs = OrderStatusLog::count();

        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_DIPROSES])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak bisa dimundurkan'));

        $this->assertSame(Order::STATUS_SIAP_DIAMBIL, $order->fresh()->status);
        $this->assertSame($logs, OrderStatusLog::count());
    }

    public function test_admin_memundurkan_status_wajib_dengan_catatan(): void
    {
        $order = $this->paidOrder(Order::STATUS_SIAP_DIAMBIL);

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_DIPROSES])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Catatan koreksi wajib'));
        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_DIPROSES, 'status_note' => '  ok  '])
            ->assertSessionHas('error');
        $this->assertSame(Order::STATUS_SIAP_DIAMBIL, $order->fresh()->status);

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_DIPROSES, 'status_note' => 'Salah klik, belum dikeringkan'])
            ->assertSessionHas('success');

        $this->assertSame(Order::STATUS_DIPROSES, $order->fresh()->status);
        $log = $this->logsFor($order)->last();
        $this->assertSame(OrderStateMachine::ACTOR_ADMIN, $log->actor_type);
        $this->assertSame('Koreksi mundur: Salah klik, belum dikeringkan', $log->note);
    }

    private function completedOrder(): Order
    {
        $order = $this->paidOrder(Order::STATUS_SIAP_DIAMBIL);
        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_SELESAI])->assertSessionHas('success');

        return $order->fresh();
    }

    public function test_pegawai_tidak_bisa_mengubah_pesanan_selesai(): void
    {
        $order = $this->completedOrder();

        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_SIAP_DIAMBIL])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah selesai'));

        $this->assertSame(Order::STATUS_SELESAI, $order->fresh()->status);
    }

    public function test_admin_mengoreksi_pesanan_selesai_dalam_24_jam_dengan_catatan(): void
    {
        $order = $this->completedOrder();
        $this->travel(23)->hours();

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Catatan koreksi wajib'));
        $this->assertSame(Order::STATUS_SELESAI, $order->fresh()->status);

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL, 'status_note' => 'Belum diambil, salah tandai'])
            ->assertSessionHas('success');

        $this->assertSame(Order::STATUS_SIAP_DIAMBIL, $order->fresh()->status);
        $log = $this->logsFor($order)->last();
        $this->assertSame([Order::STATUS_SELESAI, Order::STATUS_SIAP_DIAMBIL], [$log->from_status, $log->to_status]);
        $this->assertSame('Koreksi mundur: Belum diambil, salah tandai', $log->note);
    }

    public function test_admin_tidak_bisa_mengoreksi_pesanan_selesai_lebih_dari_24_jam(): void
    {
        $order = $this->completedOrder();
        $this->travel(24)->hours();
        $this->travel(1)->minutes();

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL, 'status_note' => 'Terlambat dikoreksi'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'lebih dari 24 jam'));

        $this->assertSame(Order::STATUS_SELESAI, $order->fresh()->status);
    }

    public function test_jendela_koreksi_dihitung_dari_selesai_terakhir(): void
    {
        $order = $this->completedOrder();
        $this->travel(20)->hours();
        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL, 'status_note' => 'Belum diambil'])->assertSessionHas('success');
        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_SELESAI])->assertSessionHas('success');

        // 30 jam sejak selesai pertama, tetapi baru 10 jam sejak selesai terakhir
        $this->travel(10)->hours();
        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL, 'status_note' => 'Koreksi kedua'])->assertSessionHas('success');
    }

    public function test_pesanan_selesai_lama_tanpa_log_memakai_updated_at(): void
    {
        $order = $this->paidOrder(Order::STATUS_SELESAI); // tanpa log transisi ke selesai

        $this->travel(25)->hours();
        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_SIAP_DIAMBIL, 'status_note' => 'Koreksi data lama'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'lebih dari 24 jam'));
    }

    public function test_form_pegawai_meminta_konfirmasi_saat_memilih_selesai(): void
    {
        $this->paidOrder();

        $this->actingAs($this->pegawai)->get(route('pegawai.orders.index'))
            ->assertOk()
            ->assertSee("confirm('Tandai pesanan '", false);
    }

    public function test_pesanan_belum_lunas_tidak_bisa_digerakkan(): void
    {
        $order = $this->makeOrder($this->pelanggan, $this->makeService());

        $this->updateAs($this->admin, $order, ['status' => Order::STATUS_DIPROSES])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'belum dibayar'));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_status_sama_tidak_menambah_log(): void
    {
        $order = $this->paidOrder(Order::STATUS_DIPROSES);
        $logs = OrderStatusLog::count();

        $this->updateAs($this->pegawai, $order, ['status' => Order::STATUS_DIPROSES])->assertSessionHas('success');

        $this->assertSame($logs, OrderStatusLog::count());
    }

    public function test_pelanggan_tidak_bisa_mengubah_status_pesanan_lewat_state_machine(): void
    {
        $order = $this->paidOrder();

        $this->expectException(InvalidStatusTransition::class);
        $this->machine->transitionOrder($order, Order::STATUS_DIPROSES, $this->pelanggan);
    }

    public function test_form_pegawai_menonaktifkan_pilihan_mundur(): void
    {
        $this->paidOrder();

        $this->actingAs($this->pegawai)->get(route('pegawai.orders.index'))
            ->assertOk()
            ->assertSee('statusRank(', false);
    }

    // ── Riwayat saat dibuat ─────────────────────────────────────

    public function test_checkout_dan_ordernya_tercatat_saat_dibuat(): void
    {
        $checkout = $this->checkoutFromCart();

        $log = $this->logsFor($checkout)->sole();
        $this->assertNull($log->from_status);
        $this->assertSame(Checkout::STATUS_AWAITING, $log->to_status);
        $this->assertSame(OrderStateMachine::ACTOR_PELANGGAN, $log->actor_type);
        $this->assertSame($this->pelanggan->id, $log->actor_id);

        $orderLog = $this->logsFor($checkout->orders()->sole())->sole();
        $this->assertSame([null, Order::STATUS_PENDING, OrderStateMachine::ACTOR_PELANGGAN], [$orderLog->from_status, $orderLog->to_status, $orderLog->actor_type]);
    }

    public function test_pesanan_kasir_tercatat_dengan_aktor_pegawai(): void
    {
        $this->actingAs($this->pegawai)->post(route('pegawai.orders.store'), [
            'service_id'       => $this->makeService(attributes: ['price' => 20000])->id,
            'machine_qty'      => 1,
            'customer_name'    => 'Walk-in',
            'payment_method'   => 'cash',
            'cash_received'    => 20000,
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHas('success');

        $log = $this->logsFor(Order::sole())->sole();
        $this->assertSame(OrderStateMachine::ACTOR_PEGAWAI, $log->actor_type);
        $this->assertSame($this->pegawai->id, $log->actor_id);
    }

    // ── Transaksi: lunas hanya dari gateway / admin ─────────────

    public function test_webhook_lunas_dicatat_sekali_walau_notifikasi_ganda(): void
    {
        $this->freezeSecond();
        $checkout = $this->checkoutFromCart();
        $notification = $this->payViaWebhook($checkout);

        foreach ([1, 2, 3] as $_) {
            app(PaymentService::class)->handleNotification('dummy', $notification['body'], $notification['headers']);
        }

        $checkout->refresh();
        $this->assertSame(Checkout::STATUS_PAID, $checkout->status);
        $this->assertTrue($checkout->paid_at->equalTo(now()));

        $paidLogs = $this->logsFor($checkout)->where('to_status', Checkout::STATUS_PAID);
        $this->assertCount(1, $paidLogs);
        $this->assertSame(OrderStateMachine::ACTOR_WEBHOOK, $paidLogs->first()->actor_type);
        $this->assertSame(Checkout::STATUS_AWAITING, $paidLogs->first()->from_status);
    }

    public function test_pelunasan_manual_admin_tercatat_dengan_aktor_admin(): void
    {
        $checkout = $this->checkoutFromCart();

        $this->actingAs($this->admin)->post(route('admin.orders.payment', $checkout->orders()->sole()), ['method' => 'cash'])
            ->assertSessionHas('success');

        $log = $this->logsFor($checkout)->last();
        $this->assertSame(Checkout::STATUS_PAID, $log->to_status);
        $this->assertSame(OrderStateMachine::ACTOR_ADMIN, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
    }

    public function test_lunas_tidak_bisa_diset_oleh_pelanggan_atau_pegawai(): void
    {
        $checkout = $this->checkoutFromCart();

        foreach ([OrderStateMachine::ACTOR_PELANGGAN, OrderStateMachine::ACTOR_PEGAWAI, OrderStateMachine::ACTOR_SCHEDULER] as $actor) {
            try {
                $this->machine->transitionCheckout($checkout, Checkout::STATUS_PAID, $actor);
                $this->fail("{$actor} seharusnya ditolak");
            } catch (InvalidStatusTransition) {
                // diharapkan
            }
        }

        $this->assertSame(Checkout::STATUS_AWAITING, $checkout->fresh()->status);
    }

    public function test_status_final_transaksi_tidak_bisa_berubah(): void
    {
        $checkout = $this->checkoutFromCart();
        $this->machine->transitionCheckout($checkout, Checkout::STATUS_EXPIRED, OrderStateMachine::ACTOR_SCHEDULER);

        foreach ([Checkout::STATUS_PAID, Checkout::STATUS_FAILED, Checkout::STATUS_AWAITING] as $to) {
            try {
                $this->machine->transitionCheckout($checkout, $to, OrderStateMachine::ACTOR_WEBHOOK);
                $this->fail("kadaluarsa → {$to} seharusnya ditolak");
            } catch (InvalidStatusTransition) {
                // diharapkan
            }
        }

        $this->assertSame(Checkout::STATUS_EXPIRED, $checkout->fresh()->status);
        // Transisi ke status yang sama = no-op
        $this->assertFalse($this->machine->transitionCheckout($checkout, Checkout::STATUS_EXPIRED, OrderStateMachine::ACTOR_SCHEDULER));
        $this->assertCount(2, $this->logsFor($checkout));
    }
}
