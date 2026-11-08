<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\CreatesLaundryData;
use Tests\TestCase;

/**
 * Form pesanan kasir: data yang tersimpan harus bisa dipertanggungjawabkan di laporan audit.
 */
class CashierOrderTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData;

    private User $pegawai;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->service = $this->makeService(attributes: ['price' => 20000]);
    }

    private function form(array $overrides = []): array
    {
        return [
            'service_id'       => $this->service->id,
            'machine_qty'      => 2,
            'customer_name'    => 'Pak Joko',
            'customer_phone'   => '0812-3344 5566',
            'payment_method'   => 'cash',
            'cash_received'    => 50000,
            'submission_token' => (string) Str::uuid(),
            ...$overrides,
        ];
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->pegawai)
            ->from(route('pegawai.orders.index'))
            ->post(route('pegawai.orders.store'), $this->form($overrides));
    }

    public function test_tunai_mencatat_uang_diterima_kembalian_dan_petugas(): void
    {
        $this->submit()->assertSessionHas('success', fn (string $message) => str_contains($message, 'Kembalian Rp 10.000'));

        $order = Order::sole();
        $payment = $order->payments()->sole();

        $this->assertSame('081233445566', $order->customer_phone);
        $this->assertEquals(40000, (float) $payment->amount);
        $this->assertSame(['paid', 'manual'], [$payment->status, $payment->gateway]);
        $this->assertSame(50000, $payment->meta['cash_received']);
        $this->assertEquals(10000, $payment->meta['change']);
        $this->assertSame($this->pegawai->id, $payment->meta['confirmed_by']);
    }

    public function test_harga_dari_klien_diabaikan(): void
    {
        $this->submit(['total_price' => 1, 'unit_price' => 1, 'amount' => 1, 'checkout_id' => 99, 'status' => 'selesai']);

        $order = Order::sole();

        $this->assertEquals(40000, (float) $order->total_price);
        $this->assertSame([null, 'pending'], [$order->checkout_id, $order->status]);
    }

    public function test_uang_kurang_ditolak_tanpa_menyisakan_data_dan_form_bisa_dikirim_ulang(): void
    {
        $this->submit(['cash_received' => 39999])->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());

        $this->actingAs($this->pegawai)->get(route('pegawai.orders.index'))->assertOk();
        $this->submit(['cash_received' => 40000])->assertSessionHas('success');
    }

    public function test_kirim_ulang_token_yang_sama_tidak_membuat_pesanan_kedua(): void
    {
        $token = (string) Str::uuid();

        $this->submit(['submission_token' => $token]);
        $this->submit(['submission_token' => $token])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'tidak dibuat ulang'));

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_token_tidak_bisa_dipakai_ulang_untuk_isi_lain_atau_petugas_lain(): void
    {
        $token = (string) Str::uuid();
        $this->submit(['submission_token' => $token]);

        $this->submit(['submission_token' => $token, 'machine_qty' => 1])->assertSessionHas('error');

        $this->pegawai = $this->makeUser(UserRole::PEGAWAI);
        $this->submit(['submission_token' => $token])->assertSessionHas('error');

        $this->assertSame(1, Order::count());
    }

    public function test_token_wajib_ada(): void
    {
        $this->submit(['submission_token' => ''])->assertSessionHasErrors('submission_token');
        $this->submit(['submission_token' => 'bukan-uuid'])->assertSessionHasErrors('submission_token');
    }

    public function test_non_tunai_wajib_nomor_bukti_dan_tidak_menyimpan_uang_tunai(): void
    {
        $this->submit(['payment_method' => 'transfer', 'cash_received' => null])
            ->assertSessionHasErrors('payment_reference');

        $this->submit(['payment_method' => 'transfer', 'payment_reference' => ' bca 123/45 ', 'cash_received' => 999])
            ->assertSessionHas('success');

        $payment = Payment::sole();

        $this->assertSame('BCA123/45', $payment->reference);
        $this->assertArrayNotHasKey('cash_received', $payment->meta);
    }

    public function test_satu_nomor_bukti_tidak_bisa_dipakai_dua_kali(): void
    {
        $this->submit(['payment_method' => 'e-wallet', 'payment_reference' => 'QR-1']);
        $this->submit(['payment_method' => 'transfer', 'payment_reference' => 'qr-1'])->assertSessionHas('error');

        $this->assertSame(1, Order::count());
    }

    public function test_nomor_bukti_berbahaya_ditolak(): void
    {
        foreach (['=SUM(A1)', '<script>', "1' OR 1=1"] as $reference) {
            $this->submit(['payment_method' => 'transfer', 'payment_reference' => $reference])
                ->assertSessionHasErrors('payment_reference');
        }
    }

    public function test_pesanan_hanya_bisa_untuk_akun_pelanggan(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->submit(['user_id' => $admin->id])->assertSessionHasErrors('user_id');
        $this->submit(['user_id' => $this->pegawai->id])->assertSessionHasErrors('user_id');

        $this->assertSame(0, Order::count());
    }

    public function test_data_member_selalu_dari_akun_bukan_dari_isian(): void
    {
        $member = $this->makeUser(attributes: ['name' => 'Sari Member', 'phone' => '081111111111']);

        $this->submit(['user_id' => $member->id, 'customer_name' => 'Nama Palsu', 'customer_phone' => '089999999999']);

        $order = Order::sole();

        $this->assertSame([$member->id, 'Sari Member', '081111111111'], [$order->user_id, $order->customer_name, $order->customer_phone]);
    }

    public function test_layanan_tidak_tersedia_dan_input_tidak_wajar_ditolak(): void
    {
        $this->service->update(['status' => 'sibuk']);
        $this->submit()->assertSessionHasErrors('service_id');
        $this->service->update(['status' => 'tersedia']);

        $this->submit(['customer_name' => '=HYPERLINK("x")'])->assertSessionHasErrors('customer_name');
        $this->submit(['customer_name' => '<img src=x>'])->assertSessionHasErrors('customer_name');
        $this->submit(['customer_phone' => '12345'])->assertSessionHasErrors('customer_phone');
        $this->submit(['item_type' => 'tidak-ada'])->assertSessionHasErrors('item_type');
        $this->submit(['cash_received' => 1000000000])->assertSessionHasErrors('cash_received');
        $this->submit(['cash_received' => '50000.5'])->assertSessionHasErrors('cash_received');

        $this->assertSame(0, Order::count());
    }

    public function test_pelanggan_ditolak_di_route_dan_di_service(): void
    {
        $customer = $this->makeUser();

        $this->actingAs($customer)->post(route('pegawai.orders.store'), $this->form())->assertForbidden();
        $this->actingAs($customer)->post(route('admin.orders.store'), $this->form())->assertForbidden();

        $this->expectException(\InvalidArgumentException::class);
        app(OrderService::class)->createWalkIn($this->form(), $customer);
    }

    public function test_error_teknis_tidak_ditampilkan_ke_layar(): void
    {
        $this->withoutExceptionHandling();
        $this->mock(OrderService::class)->shouldReceive('createWalkIn')->andThrow(new RuntimeException('SQLSTATE rahasia'));

        $this->expectException(RuntimeException::class);
        $this->submit();
    }

    public function test_batas_laju_form(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->submit(['submission_token' => '']);
        }

        $this->submit(['submission_token' => ''])->assertTooManyRequests();
    }

    public function test_nomor_hp_tersamar_di_pilihan_pelanggan(): void
    {
        $this->makeUser(attributes: ['name' => 'Sari', 'phone' => '081234567890']);

        foreach ([[UserRole::PEGAWAI, 'pegawai.orders.index'], [UserRole::ADMIN, 'admin.orders.index']] as [$role, $route]) {
            $this->actingAs($this->makeUser($role))->get(route($route))
                ->assertSee('0812****7890')
                ->assertDontSee('081234567890');
        }
    }

    public function test_form_gagal_terbuka_lagi_dengan_pesan_dan_isian(): void
    {
        $this->submit(['cash_received' => 1, 'customer_name' => 'Bu Tini']);

        $this->actingAs($this->pegawai)->get(route('pegawai.orders.index'))
            ->assertSee('Bu Tini')
            ->assertSee('Uang diterima kurang')
            ->assertSee('walkInModalOpen: true', false);
    }

    public function test_form_admin_memakai_aturan_yang_sama(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->post(route('admin.orders.store'), $this->form(['cash_received' => 1]))->assertSessionHas('error');
        $this->assertSame(0, Order::count());

        $this->actingAs($admin)->post(route('admin.orders.store'), $this->form())->assertSessionHas('success');
        $this->assertSame($admin->id, Payment::sole()->meta['confirmed_by']);
    }

    public function test_laporan_menampilkan_petugas_bukti_dan_temuan_tanpa_bukti(): void
    {
        $this->pegawai->update(['name' => 'Kasir Andi']);
        $this->submit();
        $this->submit(['payment_method' => 'transfer', 'payment_reference' => 'BCA-77']);

        $legacy = $this->makeOrder(null, $this->service, ['customer_name' => 'Lama', 'total_price' => 15000]);
        $legacy->payments()->create([
            'amount' => 15000, 'method' => 'transfer', 'gateway' => 'manual',
            'status' => 'paid', 'paid_at' => now(), 'meta' => ['confirmed_by' => $this->pegawai->id],
        ]);

        $response = $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.index', ['kanal' => 'kasir']))
            ->assertSee('Diterima Petugas Siapa?')
            ->assertSee('Tanpa bukti')
            ->assertSee('BCA-77')
            ->assertSee('Terima Rp 50.000, kembali Rp 10.000');

        $report = $response->viewData('report');

        $this->assertSame('Lama', $report['unproven']->sole()['customer']);
        $this->assertSame(95000.0, $report['cashiers']->sole()['total']);
        $this->assertSame('Kasir Andi', $report['cashiers']->sole()['label']);
        $this->assertStringContainsString('Tunai Rp 40.000', $report['cashiers']->sole()['hint']);

        $csv = $this->actingAs($this->pegawai)->get(route('pegawai.keuangan.export', ['kanal' => 'kasir']))->streamedContent();

        $this->assertStringContainsString('"Kasir Andi";50000;10', $csv);
    }
}
