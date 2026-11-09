<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\ClosedPeriod;
use App\Models\FinancialEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\MidtransNotificationService;
use App\Services\OrderStateMachine;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesFinanceData;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\MidtransFakes;
use Tests\TestCase;

/**
 * Tahap 6: pembatalan transaksi online setelah bayar + refund (API Midtrans / manual).
 */
class OnlineCancellationTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, CreatesFinanceData, MidtransFakes;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['finance.online_cancel_window_minutes' => 15]);
        $this->customer = $this->makeUser();
        $this->admin = $this->makeUser(UserRole::ADMIN);
    }

    private function paid(string $paymentType = 'qris'): Checkout
    {
        return $this->onlineCheckout($this->customer, $this->makeService(), payment: ['payment_type' => $paymentType]);
    }

    private function cancel(Checkout $checkout, array $data = [])
    {
        return $this->actingAs($this->customer)->post(route('pelanggan.transaksi.cancel', $checkout), $data);
    }

    private function fakeRefund(Checkout $checkout, mixed $response): void
    {
        $this->midtransFakes[self::API_URL . $checkout->payments()->first()->reference . '/refund'] = $response;
        $this->fakeMidtrans();
    }

    public function test_dalam_window_dan_semua_pending_refund_api_berhasil(): void
    {
        $checkout = $this->paid('qris');
        $this->fakeRefund($checkout, Http::response(['status_code' => '200', 'transaction_status' => 'refund', 'refund_amount' => '30000.00']));

        $this->actingAs($this->customer)->get(route('pelanggan.transaksi.show', $checkout))->assertSee('Batalkan Pesanan')->assertSee('data-cancel-deadline', false);

        $this->cancel($checkout)->assertRedirect(route('pelanggan.transaksi.show', $checkout))->assertSessionHas('success');

        $checkout->refresh();
        $refund = Refund::sole();
        $this->assertSame([Checkout::STATUS_PAID, Checkout::REFUND_DONE], [$checkout->status, $checkout->refund_status], 'Status final tidak diubah.');
        $this->assertSame([Refund::METHOD_API, Refund::STATUS_DONE, 30000], [$refund->method, $refund->status, $refund->amount]);
        $this->assertSame([Order::STATUS_DIBATALKAN], $checkout->orders()->pluck('status')->unique()->all());

        $entry = FinancialEntry::where('entry_type', 'refund')->sole();
        $this->assertSame([-30000, 'online', 'qris'], [$entry->amount, $entry->channel, $entry->payment_method]);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund') && $request['refund_key'] === $refund->refund_key && $request['amount'] === 30000);
        $this->artisan('ledger:verify')->assertSuccessful();
    }

    public function test_lewat_window_ditolak(): void
    {
        $checkout = $this->paid();
        $this->travel(16)->minutes();

        $this->actingAs($this->customer)->get(route('pelanggan.transaksi.show', $checkout))->assertDontSee('Batalkan Pesanan');
        $this->cancel($checkout)->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah lewat'));

        $this->assertSame(0, Refund::count());
        $this->assertSame(Checkout::REFUND_NONE, $checkout->fresh()->refund_status);
    }

    public function test_ada_item_diproses_ditolak(): void
    {
        $checkout = $this->paid();
        app(OrderStateMachine::class)->transitionOrder($checkout->orders()->first(), Order::STATUS_DIPROSES, $this->makeUser(UserRole::PEGAWAI));

        $this->cancel($checkout)->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah diterima'));
        $this->assertSame(0, Refund::count());
    }

    public function test_va_direfund_manual_dengan_rekening_lalu_admin_menandai_selesai(): void
    {
        Storage::fake('local');
        $checkout = $this->paid('bank_transfer');

        $this->cancel($checkout)->assertSessionHas('error', fn ($m) => str_contains($m, 'nomor rekening'));
        $this->cancel($checkout, ['bank_name' => 'BCA', 'account_number' => '123-456-7890', 'account_holder' => 'Budi'])->assertSessionHas('success');

        $refund = Refund::sole();
        $this->assertSame([Refund::METHOD_MANUAL, Refund::STATUS_PROCESSING, '1234567890'], [$refund->method, $refund->status, $refund->account_number]);
        $this->assertNotSame('1234567890', \DB::table('refunds')->value('account_number'), 'Nomor rekening disimpan terenkripsi.');
        $this->assertSame(0, FinancialEntry::where('entry_type', 'refund')->count(), 'Ledger refund hanya saat selesai.');

        $this->actingAs($this->admin)->get(route('admin.refunds.index'))->assertSee($checkout->code)->assertSee('Tandai Refund Selesai');
        $this->actingAs($this->makeUser(UserRole::PEGAWAI))->post(route('admin.refunds.cancellation.complete', $refund))->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.refunds.cancellation.complete', $refund), [
            'proof' => UploadedFile::fake()->image('bukti.png'), 'note' => 'Transfer BCA ref 998877',
        ])->assertSessionHas('success');

        $this->assertSame(Refund::STATUS_DONE, $refund->fresh()->status);
        $this->assertSame(Checkout::REFUND_DONE, $checkout->fresh()->refund_status);
        $this->assertSame(-30000, FinancialEntry::where('entry_type', 'refund')->sole()->amount);
    }

    public function test_refund_manual_di_periode_terkunci_ditolak(): void
    {
        Storage::fake('local');
        $checkout = $this->paid('bank_transfer');
        $this->cancel($checkout, ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi']);
        ClosedPeriod::create(['period_start' => BusinessCalendar::today(), 'period_end' => BusinessCalendar::today()]);

        $this->actingAs($this->admin)->post(route('admin.refunds.cancellation.complete', Refund::sole()), [
            'proof' => UploadedFile::fake()->image('bukti.png'), 'note' => 'Transfer BCA ref 998877',
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'ditutup buku'));

        $this->assertSame(0, FinancialEntry::where('entry_type', 'refund')->count());
    }

    public function test_refund_api_timeout_gagal_tanpa_ledger_lalu_bisa_dicoba_ulang(): void
    {
        $checkout = $this->paid('gopay');
        $this->fakeRefund($checkout, fn () => throw new ConnectionException('Operation timed out'));

        $this->cancel($checkout)->assertSessionHas('success', fn ($m) => str_contains($m, 'belum berhasil'));

        $refund = Refund::sole();
        $this->assertSame([Refund::STATUS_FAILED, 1], [$refund->status, $refund->attempts]);
        $this->assertStringContainsString('timed out', $refund->last_error);
        $this->assertSame(Checkout::REFUND_FAILED, $checkout->fresh()->refund_status);
        $this->assertSame(0, FinancialEntry::where('entry_type', 'refund')->count());

        // Coba ulang dengan refund_key yang sama
        $this->fakeRefund($checkout, Http::response(['status_code' => '200', 'transaction_status' => 'refund']));
        $this->actingAs($this->admin)->post(route('admin.refunds.cancellation.retry', $refund))->assertSessionHas('success');

        $this->assertSame([Refund::STATUS_DONE, 2], [$refund->fresh()->status, $refund->fresh()->attempts]);
        $this->assertSame(1, FinancialEntry::where('entry_type', 'refund')->count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/refund') && $r['refund_key'] === $refund->refund_key);
    }

    public function test_refund_api_ditolak_midtrans_tercatat_gagal(): void
    {
        $checkout = $this->paid('shopeepay');
        $this->fakeRefund($checkout, Http::response(['status_code' => '412', 'status_message' => 'Merchant cannot modify the status of the transaction'], 412));

        $this->cancel($checkout);

        $this->assertSame(Refund::STATUS_FAILED, Refund::sole()->status);
        $this->assertStringContainsString('cannot modify', Refund::sole()->last_error);
        $this->assertSame(0, FinancialEntry::where('entry_type', 'refund')->count());
    }

    public function test_webhook_refund_mengonfirmasi_refund_yang_gagal_sementara(): void
    {
        $this->enableMidtrans();
        $checkout = $this->paid('qris');
        $payment = $checkout->payments()->first();
        $this->fakeRefund($checkout, Http::response(['status_message' => 'Internal error'], 500));
        $this->cancel($checkout);
        $this->assertSame(Refund::STATUS_FAILED, Refund::sole()->status);

        // Midtrans ternyata memproses refund: notifikasi transaction_status=refund (dikonfirmasi API status)
        $status = $this->midtransStatus($payment, 'refund', ['payment_type' => 'qris', 'transaction_id' => 'tx-refund-1']);
        $this->fakeStatus($payment, $status);
        app(MidtransNotificationService::class)->apply($payment, $status, OrderStateMachine::ACTOR_WEBHOOK);

        $this->assertSame(Refund::STATUS_DONE, Refund::sole()->status);
        $this->assertSame(Checkout::STATUS_PAID, $checkout->fresh()->status);
        $this->assertSame(1, FinancialEntry::where('entry_type', 'refund')->count());
    }

    public function test_batal_berulang_tetap_satu_refund_dan_satu_panggilan_api(): void
    {
        $checkout = $this->paid('qris');
        $this->fakeRefund($checkout, Http::response(['status_code' => '200']));

        foreach (range(1, 3) as $i) {
            $this->cancel($checkout);
        }

        $this->assertSame(1, Refund::count());
        Http::assertSentCount(1);
        $this->assertSame(1, FinancialEntry::where('entry_type', 'refund')->count());
    }

    public function test_transaksi_milik_orang_lain_tidak_bisa_dibatalkan(): void
    {
        $checkout = $this->paid();

        $this->actingAs($this->makeUser())->post(route('pelanggan.transaksi.cancel', $checkout))->assertNotFound();
        $this->assertSame(0, Refund::count());
    }

    public function test_riwayat_pelanggan_memindahkan_transaksi_ke_tab_dibatalkan(): void
    {
        Storage::fake('local');
        $checkout = $this->paid('bank_transfer');
        $this->cancel($checkout, ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Budi']);

        $this->actingAs($this->customer)->get(route('pelanggan.orders.index', ['status' => 'dibatalkan']))->assertSee($checkout->code)->assertSee('Dibatalkan · Refund');
        $this->actingAs($this->customer)->get(route('pelanggan.orders.index', ['status' => 'diproses']))->assertDontSee($checkout->code);
    }
}
