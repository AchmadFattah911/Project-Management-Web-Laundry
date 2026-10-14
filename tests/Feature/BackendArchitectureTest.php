<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackendArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Harga & total dihitung server (abaikan input harga dari request/user).
     */
    public function test_harga_dan_total_dihitung_server_abaikan_input_harga(): void
    {
        $pelanggan = User::factory()->create(['role' => UserRole::PELANGGAN]);

        $service = Service::create([
            'name'     => 'Mesin Cuci Reguler',
            'slug'     => 'mesin-cuci-reguler',
            'category' => 'Non-Self Service',
            'unit'     => 'Mesin',
            'price'    => 15000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        // Input harga palsu disisipkan di setiap langkah: keranjang, checkout, dan bayar
        $this->actingAs($pelanggan)->post(route('pelanggan.cart.store'), ['items' => [[
            'service_id' => $service->id,
            'qty'        => 3,
            'item_type'  => 'pakaian',
            'unit_price' => 100,
            'price'      => 50,
        ]]])->assertSessionHasNoErrors();

        $this->actingAs($pelanggan)->post(route('pelanggan.checkout.store'), [
            'contact_phone'    => '081234567890',
            'drop_off_date'    => now()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
            'total_amount'     => 500,
            'total_price'      => 500,
        ])->assertSessionHasNoErrors();

        $checkout = Checkout::sole();
        $response = $this->actingAs($pelanggan)->post(route('pelanggan.pembayaran.pay', $checkout), [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method'  => 'transfer',
            'amount'          => 500,
        ]);

        // Setelah tagihan dibuat, pelanggan diarahkan ke halaman pembayaran gateway (dummy)
        $response->assertRedirectContains('/pembayaran/dummy/DUMMY-');

        $this->assertDatabaseHas('orders', [
            'user_id'     => $pelanggan->id,
            'service_id'  => $service->id,
            'machine_qty' => 3,
            'unit_price'  => 15000.00,
            'total_price' => 45000.00,
        ]);
        $this->assertEquals(45000, (float) $checkout->total_amount);

        // Tagihan online dibuat dengan nominal dari server, bukan dari input
        $this->assertDatabaseHas('payments', [
            'checkout_id' => $checkout->id,
            'amount'      => 45000.00,
            'method'      => 'transfer',
            'gateway'     => 'dummy',
            'status'      => 'pending',
        ]);
    }

    /**
     * 2. Tolak layanan tidak_tersedia.
     */
    public function test_tolak_layanan_tidak_tersedia(): void
    {
        $pelanggan = User::factory()->create(['role' => UserRole::PELANGGAN]);

        $serviceNonaktif = Service::create([
            'name'     => 'Mesin Cuci Rusak',
            'slug'     => 'mesin-cuci-rusak',
            'category' => 'Non-Self Service',
            'unit'     => 'Mesin',
            'price'    => 20000,
            'estimate' => '1 Jam',
            'status'   => 'tidak_tersedia',
        ]);

        $this->actingAs($pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $serviceNonaktif->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasErrors('items.0.service_id');

        $this->assertDatabaseMissing('cart_items', ['service_id' => $serviceNonaktif->id]);
        $this->assertDatabaseMissing('orders', ['service_id' => $serviceNonaktif->id]);

        // Jalur service tetap menolak walau dipanggil langsung
        $this->expectException(\InvalidArgumentException::class);
        app(OrderService::class)->create(['service_id' => $serviceNonaktif->id, 'machine_qty' => 1], $pelanggan);
    }

    /**
     * 3. Pelanggan tidak bisa buka order orang lain (403).
     */
    public function test_pelanggan_tidak_bisa_buka_order_orang_lain(): void
    {
        $pelangganA = User::factory()->create(['role' => UserRole::PELANGGAN]);
        $pelangganB = User::factory()->create(['role' => UserRole::PELANGGAN]);

        $service = Service::create([
            'name'     => 'Mesin Cuci A',
            'slug'     => 'mesin-cuci-a',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $orderB = Order::create([
            'order_code'  => 'LC-USERBB',
            'user_id'     => $pelangganB->id,
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($pelangganA)->get(route('pelanggan.orders.show', $orderB));

        $response->assertStatus(403);
    }

    /**
     * 4. Pegawai tidak bisa akses area admin (403).
     */
    public function test_pegawai_tidak_bisa_akses_area_admin(): void
    {
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);

        $responseDashboard = $this->actingAs($pegawai)->get(route('admin.dashboard'));
        $responseDashboard->assertStatus(403);

        $responseUsers = $this->actingAs($pegawai)->get(route('admin.users.index'));
        $responseUsers->assertStatus(403);

        $responseServices = $this->actingAs($pegawai)->get(route('admin.services.index'));
        $responseServices->assertStatus(403);
    }

    /**
     * 5. Registrasi publik tidak bisa set role (selalu pelanggan).
     */
    public function test_registrasi_publik_tidak_bisa_set_role(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Hacker Ingin Jadi Admin',
            'email'                 => 'hacker@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin', // Coba injeksi role admin
        ]);

        $this->assertAuthenticated();

        $registeredUser = User::where('email', 'hacker@example.com')->first();
        $this->assertNotNull($registeredUser);
        $this->assertSame(UserRole::PELANGGAN, $registeredUser->role);
    }

    /**
     * 6. Redirect login per role via /dashboard.
     */
    public function test_redirect_login_per_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $responseAdmin = $this->actingAs($admin)->get('/dashboard');
        $responseAdmin->assertRedirect(route('admin.dashboard'));

        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);
        $responsePegawai = $this->actingAs($pegawai)->get('/dashboard');
        $responsePegawai->assertRedirect(route('pegawai.dashboard'));

        $pelanggan = User::factory()->create(['role' => UserRole::PELANGGAN]);
        $responsePelanggan = $this->actingAs($pelanggan)->get('/dashboard');
        // Pelanggan kembali ke landing page (bisa langsung memesan dari sana)
        $responsePelanggan->assertRedirect(route('home'));
    }

    /**
     * 7. Tracking via order_code tanpa membocorkan data pribadi (tanpa nama/HP).
     */
    public function test_tracking_via_order_code_tanpa_data_pribadi(): void
    {
        $service = Service::create([
            'name'     => 'Cuci Kilat Koin',
            'slug'     => 'cuci-kilat-koin',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $order = Order::create([
            'order_code'     => 'LC-RAHASI',
            'customer_name'  => 'Budi Rahasia Kusuma',
            'customer_phone' => '089988776655',
            'service_id'     => $service->id,
            'machine_qty'    => 2,
            'unit_price'     => 10000,
            'total_price'    => 20000,
            'status'         => 'diproses',
        ]);

        $response = $this->get(route('tracking', ['order_code' => 'LC-RAHASI']));

        $response->assertOk();
        $response->assertSee('LC-RAHASI');
        $response->assertSee('Cuci Kilat Koin');
        $response->assertSee('2 unit');
        $response->assertSee('Diproses');

        // Data pribadi tidak boleh muncul di halaman tracking publik
        $response->assertDontSee('Budi Rahasia Kusuma');
        $response->assertDontSee('089988776655');
    }

    /**
     * 8. Proses otomatis sesuai layanan (Cuci Saja, Cuci Kering, Cuci Lipat, Self Service).
     */
    public function test_proses_otomatis_sesuai_layanan(): void
    {
        $orderService = app(OrderService::class);

        // Layanan 1: Cuci Saja -> ['cuci']
        $svcCuci = Service::create([
            'name'     => 'Cuci Saja Non-Self',
            'slug'     => 'cuci-saja',
            'category' => 'Non-Self Service',
            'unit'     => 'Mesin',
            'price'    => 15000,
            'estimate' => '1 Hari',
            'status'   => 'tersedia',
        ]);
        $order1 = $orderService->create([
            'service_id'     => $svcCuci->id,
            'machine_qty'    => 1,
            'customer_name'  => 'Walkin 1',
            'customer_phone' => '081111',
        ]);
        $this->assertCount(1, $order1->orderProcesses);
        $this->assertEquals(['cuci'], $order1->orderProcesses->pluck('process')->toArray());

        // Layanan 2: Cuci Kering -> ['cuci', 'kering']
        $svcKering = Service::create([
            'name'     => 'Cuci Kering Titip',
            'slug'     => 'cuci-kering-titip',
            'category' => 'Non-Self Service',
            'unit'     => 'Mesin',
            'price'    => 45000,
            'estimate' => '1 Hari',
            'status'   => 'tersedia',
        ]);
        $order2 = $orderService->create([
            'service_id'     => $svcKering->id,
            'machine_qty'    => 1,
            'customer_name'  => 'Walkin 2',
            'customer_phone' => '082222',
        ]);
        $this->assertCount(2, $order2->orderProcesses);
        $this->assertEquals(['cuci', 'kering'], $order2->orderProcesses->pluck('process')->toArray());

        // Layanan 3: Cuci Lipat -> ['cuci', 'kering', 'lipat']
        $svcLipat = Service::create([
            'name'     => 'Cuci Lipat Titip',
            'slug'     => 'cuci-lipat',
            'category' => 'Non-Self Service',
            'unit'     => 'Mesin',
            'price'    => 50000,
            'estimate' => '1 Hari',
            'status'   => 'tersedia',
        ]);
        $order3 = $orderService->create([
            'service_id'     => $svcLipat->id,
            'machine_qty'    => 1,
            'customer_name'  => 'Walkin 3',
            'customer_phone' => '083333',
        ]);
        $this->assertCount(3, $order3->orderProcesses);
        $this->assertEquals(['cuci', 'kering', 'lipat'], $order3->orderProcesses->pluck('process')->toArray());

        // Layanan 4: Self Service -> 0 proses
        $svcSelf = Service::create([
            'name'     => 'Mesin Koin Mandiri',
            'slug'     => 'mesin-koin-mandiri',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);
        $order4 = $orderService->create([
            'service_id'     => $svcSelf->id,
            'machine_qty'    => 1,
            'customer_name'  => 'Walkin 4',
            'customer_phone' => '084444',
        ]);
        $this->assertCount(0, $order4->orderProcesses);
    }

    /**
     * 9. updateMachineQty ditolak saat status siap diambil atau selesai.
     */
    public function test_update_machine_qty_ditolak_saat_status_siap_diambil_atau_selesai(): void
    {
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);
        $service = Service::create([
            'name'     => 'Cuci Reguler Status',
            'slug'     => 'cuci-reguler-status',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $orderSiapDiambil = Order::create([
            'order_code'  => 'LC-SIAP1',
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'siap_diambil',
        ]);

        $orderService = app(OrderService::class);

        try {
            $orderService->updateMachineQty($orderSiapDiambil, 3, $pegawai);
            $this->fail('Harus melempar InvalidArgumentException saat status siap diambil');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('hanya dapat diubah saat status pesanan', $e->getMessage());
        }

        $orderSelesai = Order::create([
            'order_code'  => 'LC-SELESAI1',
            'service_id'  => $service->id,
            'machine_qty' => 2,
            'unit_price'  => 10000,
            'total_price' => 20000,
            'status'      => 'selesai',
        ]);

        $response = $this->actingAs($pegawai)->put(route('pegawai.orders.update', $orderSelesai), [
            'machine_qty' => 4,
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(2, $orderSelesai->fresh()->machine_qty);
    }

    /**
     * 10. Pelanggan tidak bisa set pembayaran jadi paid.
     */
    public function test_pelanggan_tidak_bisa_set_pembayaran_jadi_paid(): void
    {
        $pelanggan = User::factory()->create(['role' => UserRole::PELANGGAN]);
        $service = Service::create([
            'name'     => 'Cuci Koin Pay',
            'slug'     => 'cuci-koin-pay',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $order = Order::create([
            'order_code'  => 'LC-PAYTEST',
            'user_id'     => $pelanggan->id,
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'pending',
        ]);

        // Route konfirmasi pembayaran hanya untuk pegawai/admin
        $response = $this->actingAs($pelanggan)->post(route('pegawai.orders.payment', $order), [
            'method' => 'cash',
        ]);

        $response->assertForbidden();

        // Policy PaymentPolicy update menolak pelanggan
        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'amount'   => 10000,
            'method'   => 'cash',
            'status'   => 'pending',
        ]);

        $this->assertFalse($pelanggan->can('update', $payment));
        $this->assertFalse($pelanggan->can('create', \App\Models\Payment::class));
    }

    /**
     * 11. Pegawai bisa konfirmasi pembayaran.
     */
    public function test_pegawai_bisa_konfirmasi_pembayaran(): void
    {
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);
        $service = Service::create([
            'name'     => 'Cuci Koin Kasir',
            'slug'     => 'cuci-koin-kasir',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 15000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $order = Order::create([
            'order_code'  => 'LC-KONF123',
            'service_id'  => $service->id,
            'machine_qty' => 2,
            'unit_price'  => 15000,
            'total_price' => 30000,
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($pegawai)->post(route('pegawai.orders.payment', $order), [
            'method' => 'cash',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status'   => 'paid',
            'method'   => 'cash',
            'amount'   => 30000,
        ]);

        $payment = $order->fresh()->payments()->first();
        $this->assertNotNull($payment->paid_at);
        $this->assertTrue($pegawai->can('update', $payment));
    }

    /**
     * 13. Pegawai 403 di semua route admin.
     */
    public function test_pegawai_403_di_semua_route_admin(): void
    {
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);

        $this->actingAs($pegawai)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('admin.services.index'))->assertForbidden();
    }

    /**
     * 14. Admin tidak bisa menurunkan role dirinya sendiri.
     */
    public function test_admin_tidak_bisa_menurunkan_role_dirinya_sendiri(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name'  => $admin->name,
            'email' => $admin->email,
            'role'  => 'pegawai',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(UserRole::ADMIN, $admin->fresh()->role);
    }

    /**
     * 15. Tidak bisa menghapus atau menurunkan role admin terakhir.
     */
    public function test_tidak_bisa_menghapus_atau_menurunkan_admin_terakhir(): void
    {
        // Pastikan hanya ada 1 admin
        User::where('role', UserRole::ADMIN->value)->delete();
        $singleAdmin = User::factory()->create(['role' => UserRole::ADMIN]);
        $anotherAdmin = User::factory()->create(['role' => UserRole::ADMIN]);

        // When another admin tries to delete singleAdmin
        $response = $this->actingAs($anotherAdmin)->delete(route('admin.users.destroy', $singleAdmin));
        $response->assertSessionHas('success');

        // Now singleAdmin is deleted, anotherAdmin is the only admin
        $this->assertEquals(1, User::where('role', UserRole::ADMIN->value)->count());

        // When attempting to change anotherAdmin's role to pelanggan via acting as admin
        $response = $this->actingAs($anotherAdmin)->put(route('admin.users.update', $anotherAdmin), [
            'name'  => $anotherAdmin->name,
            'email' => $anotherAdmin->email,
            'role'  => 'pelanggan',
        ]);
        $response->assertSessionHas('error');
        $this->assertEquals(UserRole::ADMIN, $anotherAdmin->fresh()->role);
    }

    /**
     * 16. Layanan terpakai tidak bisa dihapus.
     */
    public function test_layanan_terpakai_tidak_bisa_dihapus(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = Service::create([
            'name'     => 'Cuci Terpakai',
            'slug'     => 'cuci-terpakai',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        Order::create([
            'order_code'  => 'LC-SVCORDER',
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.services.destroy', $service));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    /**
     * 17. Ubah harga layanan tidak mengubah total order lama.
     */
    public function test_ubah_harga_layanan_tidak_mengubah_total_order_lama(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = Service::create([
            'name'     => 'Cuci Snapshot Test',
            'slug'     => 'cuci-snapshot-test',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 15000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $order = Order::create([
            'order_code'  => 'LC-SNAPSHOT1',
            'service_id'  => $service->id,
            'machine_qty' => 2,
            'unit_price'  => 15000,
            'total_price' => 30000,
            'status'      => 'pending',
        ]);

        // Admin mengubah harga layanan menjadi 35.000
        $response = $this->actingAs($admin)->put(route('admin.services.update', $service), [
            'name'        => $service->name,
            'category'    => $service->category,
            'price'       => 35000,
            'estimate'    => $service->estimate,
            'unit'        => $service->unit,
            'status'      => 'tersedia',
            'description' => 'Harga baru dinaikkan',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(35000, $service->fresh()->price);

        // Snapshot pada order lama harus tetap 15.000 dan 30.000
        $orderFresh = $order->fresh();
        $this->assertEquals(15000, $orderFresh->unit_price);
        $this->assertEquals(30000, $orderFresh->total_price);
    }

    /**
     * 18. Order walk-in yang dibayar langsung membuat baris payments paid dengan paid_at.
     */
    public function test_order_walkin_dibayar_langsung_membuat_baris_payment_paid(): void
    {
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);
        $service = Service::create([
            'name'     => 'Cuci Walkin Paid',
            'slug'     => 'cuci-walkin-paid',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 20000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $response = $this->actingAs($pegawai)->post(route('pegawai.orders.store'), [
            'service_id'     => $service->id,
            'machine_qty'    => 2,
            'customer_name'  => 'Pak Joko Walkin',
            'customer_phone' => '081233445566',
            'payment_method' => 'cash',
            'is_paid'        => '1',
        ]);

        $response->assertSessionHas('success');

        $order = Order::where('customer_name', 'Pak Joko Walkin')->first();
        $this->assertNotNull($order);
        $this->assertEquals(40000, $order->total_price);

        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals('cash', $payment->method);
        $this->assertEquals(40000, (float) $payment->amount);
        $this->assertNotNull($payment->paid_at);
    }

    /**
     * 19. Admin TIDAK boleh menghapus order yang sudah memiliki pembayaran paid.
     */
    public function test_admin_tidak_boleh_menghapus_order_yang_sudah_dibayar_lunas(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = Service::create([
            'name'     => 'Cuci Paid Protect',
            'slug'     => 'cuci-paid-protect',
            'category' => 'Self Service',
            'unit'     => 'Mesin',
            'price'    => 10000,
            'estimate' => '1 Jam',
            'status'   => 'tersedia',
        ]);

        $order = Order::create([
            'order_code'  => 'LC-PAIDDEL',
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'selesai',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'amount'   => 10000,
            'method'   => 'cash',
            'status'   => 'paid',
            'paid_at'  => now(),
        ]);

        // Policy delete must return false
        $this->assertFalse($admin->can('delete', $order));

        // Attempting to hit delete route returns 403 Forbidden
        $response = $this->actingAs($admin)->delete(route('admin.orders.destroy', $order));
        $response->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
