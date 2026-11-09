<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Tahap 2: data pelanggan untuk kasir (tambah walk-in, ubah kontak, nonaktifkan, gabung, riwayat).
 */
class CustomerManagementTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pegawai;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->admin = $this->makeUser(UserRole::ADMIN);
    }

    private function storeCustomer(array $data = [], ?string $token = null)
    {
        return $this->actingAs($this->pegawai)->post(route('panel.pelanggan.store'), $data + [
            'name' => 'Bu Tini', 'phone' => '0812-3456-7890', 'notes' => 'Langganan', 'submission_token' => $token ?? (string) Str::uuid(),
        ]);
    }

    public function test_pegawai_menambah_pelanggan_walk_in_dengan_hp_ternormalisasi(): void
    {
        $this->storeCustomer()->assertRedirect()->assertSessionHas('success');

        $customer = User::where('name', 'Bu Tini')->sole();
        $this->assertSame('+6281234567890', $customer->phone_e164);
        $this->assertTrue($customer->isPelanggan());
        $this->assertTrue($customer->isWalkIn());
        $this->assertStringEndsWith('.invalid', $customer->email);
        $this->assertTrue(AuditLog::where('action', 'pelanggan.create')->where('subject_id', $customer->id)->exists());

        // Akun walk-in tidak bisa dipakai login
        $this->post('/logout');
        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertSessionHasErrors();
    }

    public function test_submit_ganda_menghasilkan_satu_pelanggan(): void
    {
        $token = (string) Str::uuid();
        $this->storeCustomer([], $token);
        $this->storeCustomer([], $token)->assertSessionHas('success', fn ($m) => str_contains($m, 'tidak dibuat ulang'));

        $this->assertSame(1, User::where('name', 'Bu Tini')->count());
    }

    public function test_nomor_hp_unik_dan_wajib_valid(): void
    {
        $this->storeCustomer();
        $this->storeCustomer(['name' => 'Orang Lain', 'phone' => '+62 812 3456 7890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Bu Tini'));
        $this->storeCustomer(['name' => 'Tanpa HP', 'phone' => ''])->assertSessionHasErrors('phone');
        $this->storeCustomer(['name' => 'HP Salah', 'phone' => '021555'])->assertSessionHasErrors('phone');

        $this->assertSame(1, User::where('role', 'pelanggan')->count());
    }

    public function test_ubah_kontak_tercatat_di_audit(): void
    {
        $this->storeCustomer();
        $customer = User::where('name', 'Bu Tini')->sole();

        $this->actingAs($this->pegawai)->put(route('panel.pelanggan.update', $customer), ['name' => 'Bu Tini S', 'phone' => '081299998888'])
            ->assertSessionHas('success');

        $this->assertSame('+6281299998888', $customer->fresh()->phone_e164);
        $log = AuditLog::where('action', 'pelanggan.update')->sole();
        $this->assertSame('Bu Tini', $log->before['name']);
        $this->assertSame('Bu Tini S', $log->after['name']);
    }

    public function test_hanya_admin_yang_menonaktifkan_dan_tidak_ada_hapus(): void
    {
        $customer = $this->makeUser(attributes: ['phone' => '081200001111']);

        $this->actingAs($this->pegawai)->post(route('panel.pelanggan.deactivate', $customer), ['reason' => 'pelanggan pindah kota'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('panel.pelanggan.deactivate', $customer), ['reason' => 'singkat'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('panel.pelanggan.deactivate', $customer), ['reason' => 'pelanggan pindah kota'])->assertSessionHas('success');

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertNotNull(User::find($customer->id), 'Tidak ada hapus permanen.');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('panel.pelanggan.destroy'));
    }

    public function test_admin_menggabungkan_pelanggan_ganda(): void
    {
        $primary = $this->makeUser(attributes: ['name' => 'Budi']);
        $duplicate = $this->makeUser(attributes: ['name' => 'Budi (outlet)']);
        $duplicate->forceFill(['phone_e164' => '+6281277778888'])->save();
        $service = $this->makeService();
        $this->makeOrder($duplicate, $service);
        Checkout::create(['code' => 'TRX-GABUNG01', 'user_id' => $duplicate->id, 'total_amount' => 1000, 'status' => 'lunas', 'contact_phone' => '0812', 'submission_token' => (string) Str::uuid()]);

        $this->actingAs($this->pegawai)->post(route('panel.pelanggan.merge'), ['primary_id' => $primary->id, 'duplicate_id' => $duplicate->id, 'reason' => 'akun ganda pelanggan sama'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('panel.pelanggan.merge'), [
            'primary_id' => $primary->id, 'duplicate_id' => $duplicate->id, 'reason' => 'akun ganda pelanggan sama',
        ])->assertRedirect(route('panel.pelanggan.show', $primary));

        $this->assertSame(2, Order::where('user_id', $primary->id)->count() + Checkout::where('user_id', $primary->id)->count());
        $duplicate->refresh();
        $this->assertFalse($duplicate->is_active);
        $this->assertSame($primary->id, $duplicate->merged_into_id);
        $this->assertSame('+6281277778888', $primary->fresh()->phone_e164);

        $log = AuditLog::where('action', 'pelanggan.merge')->sole();
        $this->assertEquals(['checkouts' => 1, 'orders' => 1, 'cart_items' => 0], $log->after['moved']);
        $this->assertSame('akun ganda pelanggan sama', $log->reason);
    }

    public function test_detail_pelanggan_menampilkan_riwayat_dua_saluran(): void
    {
        $customer = $this->makeUser();
        Checkout::create(['code' => 'TRX-ONLINE01', 'user_id' => $customer->id, 'total_amount' => 1000, 'status' => 'lunas', 'contact_phone' => '0812', 'submission_token' => (string) Str::uuid()]);
        $outlet = Checkout::create(['code' => 'TRX-OUTLET01', 'user_id' => $customer->id, 'total_amount' => 2000, 'status' => 'lunas', 'contact_phone' => '0812', 'submission_token' => (string) Str::uuid()]);
        $outlet->forceFill(['channel' => 'outlet'])->save();
        $this->makeOrder($customer, $this->makeService(), ['order_code' => 'LC-LAMA01']);

        $this->actingAs($this->pegawai)->get(route('panel.pelanggan.show', $customer))
            ->assertOk()
            ->assertSee('TRX-ONLINE01')
            ->assertSee('TRX-OUTLET01')
            ->assertSee('LC-LAMA01');

        // Akun staf bukan pelanggan → 404
        $this->actingAs($this->pegawai)->get(route('panel.pelanggan.show', $this->admin))->assertNotFound();
    }

    public function test_pencarian_nama_dan_hp(): void
    {
        $this->storeCustomer();
        $this->makeUser(attributes: ['name' => 'Siti']);

        $this->actingAs($this->pegawai)->get(route('panel.pelanggan.index', ['search' => '081234567890']))
            ->assertSee('Bu Tini')->assertDontSee('Siti');

        $this->actingAs($this->pegawai)->getJson(route('panel.pelanggan.search', ['q' => 'tini']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Bu Tini');
    }

    public function test_pelanggan_tidak_bisa_membuka_panel(): void
    {
        $this->actingAs($this->makeUser())->get(route('panel.pelanggan.index'))->assertForbidden();
    }

    public function test_hapus_akun_sendiri_yang_punya_transaksi_menjadi_nonaktif(): void
    {
        $customer = $this->makeUser();
        $this->makeOrder($customer, $this->makeService());

        $this->actingAs($customer)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertFalse($customer->fresh()->is_active);
    }
}
