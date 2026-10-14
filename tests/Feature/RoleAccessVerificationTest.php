<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiap_role_hanya_dapat_mengakses_halamannya_sendiri(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $pegawai = User::factory()->create(['role' => UserRole::PEGAWAI]);
        $pelanggan = User::factory()->create(['role' => UserRole::PELANGGAN]);

        // 1. Admin bisa akses halaman admin
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        // 2. Pegawai bisa akses area pegawai, tapi ditolak 403 di area admin
        $this->actingAs($pegawai)->get(route('pegawai.dashboard'))->assertOk();
        $this->actingAs($pegawai)->get(route('pegawai.orders.index'))->assertOk();
        $this->actingAs($pegawai)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($pegawai)->get(route('admin.orders.index'))->assertStatus(403);
        $this->actingAs($pegawai)->get(route('admin.services.index'))->assertStatus(403);
        $this->actingAs($pegawai)->get(route('admin.users.index'))->assertStatus(403);

        // 3. Pelanggan bisa akses area pelanggan, tapi ditolak 403 di pegawai & admin
        $this->actingAs($pelanggan)->get(route('pelanggan.akun'))->assertOk();
        $this->actingAs($pelanggan)->get(route('pelanggan.orders.index'))->assertOk();
        $this->actingAs($pelanggan)->get(route('pegawai.dashboard'))->assertStatus(403);
        $this->actingAs($pelanggan)->get(route('pegawai.orders.index'))->assertStatus(403);
        $this->actingAs($pelanggan)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($pelanggan)->get(route('admin.orders.index'))->assertStatus(403);
    }

    public function test_pelanggan_dilarang_membuka_pesanan_orang_lain(): void
    {
        $pelanggan1 = User::factory()->create(['role' => UserRole::PELANGGAN]);
        $pelanggan2 = User::factory()->create(['role' => UserRole::PELANGGAN]);

        $service = Service::create([
            'name'        => 'Cuci Mandiri',
            'slug'        => 'cuci-mandiri',
            'category'    => 'Self Service',
            'unit'        => 'Mesin',
            'price'       => 10000,
            'is_active'   => true,
            'description' => 'Test',
        ]);

        $orderPelanggan2 = Order::create([
            'order_code'  => 'LC-ORD999',
            'user_id'     => $pelanggan2->id,
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => 10000,
            'total_price' => 10000,
            'status'      => 'pending',
        ]);

        // Pelanggan 1 akses pesanan pelanggan 2 -> 403 Forbidden
        $this->actingAs($pelanggan1)
            ->get(route('pelanggan.orders.show', $orderPelanggan2))
            ->assertStatus(403);

        // Pelanggan 2 akses pesanan sendiri -> 200 OK
        $this->actingAs($pelanggan2)
            ->get(route('pelanggan.orders.show', $orderPelanggan2))
            ->assertOk();
    }

    public function test_registrasi_baru_selalu_berperan_sebagai_pelanggan(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Pengguna Baru',
            'email'                 => 'pengguna.baru@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin', // usaha injeksi role
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'pengguna.baru@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::PELANGGAN, $user->role);
    }
}
