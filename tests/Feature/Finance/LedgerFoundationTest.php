<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\ClosedPeriod;
use App\Models\FinancialEntry;
use App\Models\LedgerReview;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\DummyGateway;
use App\Services\AuditLogger;
use App\Services\LedgerService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\UsesDummyPayments;
use Tests\TestCase;

/**
 * Tahap 1: saluran ditentukan sistem, ledger append-only, backfill/verify, permission, tutup buku.
 */
class LedgerFoundationTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, UsesDummyPayments;

    private function paidCheckout(User $user, int $amount = 30000, array $payment = []): Payment
    {
        $checkout = Checkout::create([
            'code' => 'TRX-' . strtoupper(Str::random(8)), 'user_id' => $user->id, 'total_amount' => $amount,
            'status' => Checkout::STATUS_PAID, 'paid_at' => now(), 'contact_phone' => '081234567890',
            'submission_token' => (string) Str::uuid(),
        ]);

        return $checkout->payments()->create($payment + [
            'amount' => $amount, 'method' => 'transfer', 'gateway' => 'dummy', 'reference' => $checkout->code . '-P1',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);
    }

    public function test_walk_in_kasir_mencatat_penjualan_outlet_di_ledger(): void
    {
        $pegawai = $this->makeUser(UserRole::PEGAWAI);
        $service = $this->makeService(attributes: ['price' => 20000]);

        app(OrderService::class)->createWalkIn([
            'service_id' => $service->id, 'machine_qty' => 2, 'customer_name' => 'Bu Tini', 'customer_phone' => '0812',
            'payment_method' => 'cash', 'cash_received' => 50000, 'submission_token' => (string) Str::uuid(),
            'channel' => 'online', // diabaikan: saluran ditentukan sistem
        ], $pegawai);

        $order = Order::latest('id')->first();
        $this->assertSame(Order::CHANNEL_OUTLET, $order->channel);

        $entry = FinancialEntry::sole();
        $this->assertSame(['sale', 'outlet', 40000, 'cash', $order->id], [$entry->entry_type, $entry->channel, $entry->amount, $entry->payment_method, $entry->order_id]);
    }

    public function test_checkout_online_lunas_lewat_webhook_dicatat_sekali(): void
    {
        $pelanggan = $this->makeUser();
        $checkout = Checkout::create([
            'code' => 'TRX-WEBHOOK1', 'channel' => 'outlet', 'user_id' => $pelanggan->id, 'total_amount' => 25000,
            'status' => Checkout::STATUS_AWAITING, 'contact_phone' => '0812', 'submission_token' => (string) Str::uuid(),
        ]);
        $checkout->forceFill(['channel' => 'online'])->save();
        $payment = $checkout->payments()->create(['amount' => 25000, 'method' => 'transfer', 'gateway' => 'dummy', 'reference' => 'TRX-WEBHOOK1-P1', 'status' => 'pending']);

        $notification = app(DummyGateway::class)->buildNotification($payment, 'paid');
        foreach ([1, 2] as $attempt) {
            $this->call('POST', route('payments.webhook', 'dummy'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DUMMY_SIGNATURE' => $notification['headers'][DummyGateway::SIGNATURE_HEADER],
            ], $notification['body'])->assertOk();
        }

        $entry = FinancialEntry::sole();
        $this->assertSame(['sale', 'online', 25000, $checkout->id], [$entry->entry_type, $entry->channel, $entry->amount, $entry->checkout_id]);
    }

    public function test_pembayaran_midtrans_mencatat_estimasi_biaya_gateway(): void
    {
        config(['finance.gateway_fees.qris' => ['flat' => 0, 'bps' => 70]]);
        $payment = $this->paidCheckout($this->makeUser(), 100000, ['gateway' => 'midtrans', 'payment_type' => 'qris']);

        app(LedgerService::class)->recordSale($payment);
        app(LedgerService::class)->recordSale($payment);

        $this->assertSame(2, FinancialEntry::count());
        $this->assertSame(-700, FinancialEntry::where('entry_type', 'gateway_fee')->value('amount'));
        $this->assertSame('qris', FinancialEntry::where('entry_type', 'sale')->value('payment_method'));
    }

    public function test_tanggal_bisnis_memakai_zona_waktu_outlet(): void
    {
        $user = $this->makeUser();
        // 23.30 WIB 10 Nov = 16.30 UTC; 00.30 WIB 11 Nov = 17.30 UTC 10 Nov
        $late = $this->paidCheckout($user, 10000, ['paid_at' => Carbon::parse('2026-11-10 16:30:00', 'UTC')]);
        $early = $this->paidCheckout($user, 10000, ['paid_at' => Carbon::parse('2026-11-10 17:30:00', 'UTC')]);

        $ledger = app(LedgerService::class);
        $this->assertSame('2026-11-10', $ledger->recordSale($late)->business_date->toDateString());
        $this->assertSame('2026-11-11', $ledger->recordSale($early)->business_date->toDateString());
    }

    public function test_penjualan_di_periode_tutup_buku_diakui_di_periode_berjalan(): void
    {
        $this->travelTo(Carbon::parse('2026-12-02 03:00:00', 'UTC'));
        ClosedPeriod::create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
        $payment = $this->paidCheckout($this->makeUser(), 10000, ['paid_at' => Carbon::parse('2026-11-30 10:00:00', 'UTC')]);

        $this->assertSame('2026-12-02', app(LedgerService::class)->recordSale($payment)->business_date->toDateString());
    }

    public function test_backfill_idempoten_dan_verify_nol_selisih(): void
    {
        $user = $this->makeUser();
        // Data lama: dibuat langsung tanpa ledger
        $this->paidCheckout($user, 30000);
        $this->paidCheckout($user, 45000, ['gateway' => 'midtrans', 'payment_type' => 'gopay']);
        $order = $this->makeOrder(null, $this->makeService());
        $order->payments()->create(['amount' => 15000, 'method' => 'cash', 'gateway' => 'manual', 'status' => 'paid', 'paid_at' => now()]);
        $order->payments()->create(['amount' => 15000, 'method' => 'cash', 'gateway' => 'manual', 'status' => 'failed']);

        $this->artisan('ledger:verify')->assertFailed();
        $this->assertSame(3, LedgerReview::where('issue', 'missing_sale')->count(), 'Selisih ditandai perlu_tinjau.');

        $this->artisan('ledger:backfill')->assertSuccessful();
        $this->artisan('ledger:backfill')->assertSuccessful();

        $this->assertSame(3, FinancialEntry::where('entry_type', 'sale')->count());
        $this->assertSame(1, FinancialEntry::where('entry_type', 'gateway_fee')->count());
        $this->assertSame(90000, (int) FinancialEntry::where('entry_type', 'sale')->sum('amount'));
        $this->artisan('ledger:verify')->expectsOutputToContain('0 selisih')->assertSuccessful();
    }

    public function test_verify_mendeteksi_nominal_berbeda(): void
    {
        $payment = $this->paidCheckout($this->makeUser(), 30000);
        app(LedgerService::class)->recordSale($payment);
        Payment::whereKey($payment->id)->update(['amount' => 35000]);

        $this->artisan('ledger:verify')->assertFailed();
        $this->assertTrue(LedgerReview::where('issue', 'sale_amount_mismatch')->where('status', 'perlu_tinjau')->exists());
    }

    public function test_ledger_dan_audit_log_append_only(): void
    {
        $entry = app(LedgerService::class)->recordSale($this->paidCheckout($this->makeUser()));
        $log = app(AuditLogger::class)->log('uji.aksi', $entry, null, ['a' => 1], 'alasan');

        foreach ([$entry, $log] as $model) {
            try {
                $model->update([$model instanceof FinancialEntry ? 'amount' : 'reason' => 999]);
                $this->fail('Update harus ditolak.');
            } catch (LogicException) {
            }

            try {
                $model->delete();
                $this->fail('Delete harus ditolak.');
            } catch (LogicException) {
            }
        }

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('financial_entry', strtolower(Str::snake(class_basename($log->subject_type))));
    }

    public function test_permission_per_role_dan_akun_nonaktif(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);
        $pegawai = $this->makeUser(UserRole::PEGAWAI);
        $pelanggan = $this->makeUser();

        $this->assertTrue($admin->can('laporan.view'));
        $this->assertTrue($admin->can('transaksi.void.approve'));
        $this->assertTrue($pegawai->can('transaksi.void.request'));
        $this->assertFalse($pegawai->can('transaksi.void.approve'));
        $this->assertFalse($pegawai->can('laporan.view'));
        $this->assertFalse($pegawai->can('laporan.export'));
        $this->assertFalse($pelanggan->can('transaksi.outlet.view'));

        $admin->forceFill(['is_active' => false])->save();
        $this->assertFalse($admin->fresh()->can('laporan.view'));
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'nonaktif@example.com']);
        $user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => 'nonaktif@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
