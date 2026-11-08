<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentNotificationLog;
use App\Models\User;
use App\Services\MidtransNotificationService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\MidtransFakes;
use Tests\TestCase;

/**
 * Tahap 5: webhook Midtrans idempoten, rekonsiliasi, job kedaluwarsa, cek status admin, Perlu Refund.
 */
class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryData, MidtransFakes;

    private User $pelanggan;

    private Checkout $checkout;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableMidtrans();
        $this->freezeSecond();

        $this->pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $this->checkout = $this->makeCartCheckout($this->pelanggan);
        $this->payment = $this->openSnap($this->checkout, $this->pelanggan);
    }

    private function paidLogs(): int
    {
        return $this->checkout->statusLogs()->where('to_status', Checkout::STATUS_PAID)->count();
    }

    // ── Webhook: urutan, signature, dedup ───────────────────────────

    public function test_settlement_dikirim_tiga_kali_lunas_sekali_dan_satu_log(): void
    {
        $settlement = $this->midtransStatus($this->payment, 'settlement');
        $this->fakeStatus($this->payment, $settlement);

        foreach (range(1, 3) as $i) {
            $this->sendMidtransWebhook($settlement)->assertOk();
        }

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertSame(1, $this->paidLogs());
        $this->assertSame(Payment::STATUS_PAID, $this->payment->fresh()->status);
        $this->assertSame(1, PaymentEvent::where('gateway', 'midtrans')->count());
        $this->assertSame(['applied', 'duplicate', 'duplicate'], PaymentNotificationLog::orderBy('id')->pluck('result')->all());

        $log = $this->checkout->statusLogs()->where('to_status', Checkout::STATUS_PAID)->sole();
        $this->assertSame(OrderStateMachine::ACTOR_WEBHOOK, $log->actor_type);
    }

    public function test_pending_lalu_settlement_dengan_transaction_id_sama_menjadi_lunas(): void
    {
        $pending = $this->midtransStatus($this->payment, 'pending', ['transaction_id' => 'tx-sama']);
        $settlement = $this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-sama']);

        $this->fakeStatus($this->payment, [$pending, $settlement]);

        $this->sendMidtransWebhook($pending)->assertOk();

        $checkout = $this->checkout->fresh();
        $payment = $this->payment->fresh();
        $this->assertSame(Checkout::STATUS_AWAITING, $checkout->status);
        $this->assertSame('menunggu_transfer', $checkout->pending_reason);
        $this->assertSame('tx-sama', $payment->transaction_id);
        $this->assertSame('bank_transfer', $payment->payment_type);
        $this->assertSame('transfer', $payment->method);
        $this->assertSame(['bank' => 'BCA', 'number' => '12345678901'], $payment->paymentInstruction());

        $this->sendMidtransWebhook($settlement)->assertOk();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertNull($this->checkout->fresh()->pending_reason);
        $this->assertSame(['tx-sama:pending', 'tx-sama:settlement'], PaymentEvent::orderBy('id')->pluck('event_id')->all());
    }

    public function test_signature_salah_dijawab_403_dan_tetap_disimpan(): void
    {
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'), signature: str_repeat('a', 128))
            ->assertForbidden();

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);

        $record = PaymentNotificationLog::sole();
        $this->assertFalse($record->signature_valid);
        $this->assertSame(MidtransNotificationService::RESULT_INVALID_SIGNATURE, $record->result);
        $this->assertSame($this->payment->reference, $record->order_id);

        // Tidak ada konfirmasi ke API status untuk notifikasi palsu
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/status'));
    }

    public function test_payload_rusak_dijawab_400(): void
    {
        $this->call('POST', route('payments.webhook', 'midtrans'), [], [], [], ['CONTENT_TYPE' => 'application/json'], 'bukan json')
            ->assertStatus(400);

        $this->assertSame(MidtransNotificationService::RESULT_INVALID_PAYLOAD, PaymentNotificationLog::sole()->result);
    }

    public function test_status_dari_api_adalah_sumber_kebenaran(): void
    {
        // Notifikasi (bertanda tangan sah) mengaku settlement, tetapi API status masih pending
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'pending'));

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))->assertOk();

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $this->payment->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->url() === self::API_URL . $this->payment->reference . '/status' && $r->method() === 'GET');
    }

    public function test_nominal_beda_tidak_lunas_dan_ditandai(): void
    {
        $status = $this->midtransStatus($this->payment, 'settlement', ['gross_amount' => '1000.00']);
        $this->fakeStatus($this->payment, $status);

        $this->sendMidtransWebhook($status)->assertOk();

        $payment = $this->payment->fresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Payment::FLAG_AMOUNT_MISMATCH, $payment->review_flag);
        $this->assertSame(1000, $payment->meta['flagged'][0]['gross_amount']);
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(0, $this->paidLogs());
    }

    public function test_gross_amount_berformat_desimal_dibandingkan_sebagai_integer(): void
    {
        $total = $this->checkout->totalRupiah();
        $status = $this->midtransStatus($this->payment, 'settlement', ['gross_amount' => "{$total}.00"]);
        $this->fakeStatus($this->payment, $status);

        $this->sendMidtransWebhook($status)->assertOk();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertNull($this->payment->fresh()->review_flag);
    }

    public function test_settlement_lalu_pending_terlambat_tetap_lunas(): void
    {
        $settlement = $this->midtransStatus($this->payment, 'settlement');
        $latePending = $this->midtransStatus($this->payment, 'pending');
        $this->fakeStatus($this->payment, [$settlement, $latePending]);

        $this->sendMidtransWebhook($settlement)->assertOk();
        $this->sendMidtransWebhook($latePending)->assertOk();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $this->payment->fresh()->status);
        $this->assertSame(1, $this->paidLogs());
        $this->assertSame('no_change', PaymentNotificationLog::orderByDesc('id')->value('result'));
    }

    public function test_dua_tagihan_berbeda_sama_sama_settlement_satu_diakui_satu_perlu_refund(): void
    {
        // P1 ditutup sistem (dianggap macet) → pelanggan membuka P2; ternyata keduanya dibayar
        $first = $this->payment;
        $first->update(['status' => Payment::STATUS_FAILED]);
        $second = $this->openSnap($this->checkout, $this->pelanggan, 'snap-token-2');
        $this->assertNotSame($first->reference, $second->reference);

        $this->fakeStatus($second, $this->midtransStatus($second, 'settlement', ['transaction_id' => 'tx-B']));
        $this->fakeStatus($first, $this->midtransStatus($first, 'settlement', ['transaction_id' => 'tx-A']));

        $this->sendMidtransWebhook($this->midtransStatus($second, 'settlement', ['transaction_id' => 'tx-B']))->assertOk();
        $this->sendMidtransWebhook($this->midtransStatus($first, 'settlement', ['transaction_id' => 'tx-A']))->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $second->fresh()->status);
        $this->assertSame(Payment::FLAG_DUPLICATE_PAID, $first->fresh()->review_flag);
        $this->assertNotSame(Payment::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertSame(1, $this->paidLogs());
        $this->assertSame(1, $this->checkout->payments()->where('status', Payment::STATUS_PAID)->count());
    }

    public function test_order_id_sama_dibayar_dengan_transaction_id_lain_ditandai_ganda(): void
    {
        $this->fakeStatus($this->payment, [
            $this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-A']),
            $this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-C']),
        ]);

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-A']))->assertOk();
        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-C']))->assertOk();

        $payment = $this->payment->fresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('tx-A', $payment->transaction_id);
        $this->assertSame(Payment::FLAG_DUPLICATE_PAID, $payment->review_flag);
        $this->assertSame('tx-C', $payment->meta['flagged'][0]['transaction_id']);
        $this->assertSame(1, $this->paidLogs());
    }

    public function test_transaction_id_boleh_berganti_untuk_order_id_yang_sama(): void
    {
        // Pelanggan memilih VA BCA lalu mengganti ke QRIS di Snap: transaction_id baru untuk order_id yang sama
        $this->fakeStatus($this->payment, [
            $this->midtransStatus($this->payment, 'pending', ['transaction_id' => 'tx-bca']),
            $this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-qris', 'payment_type' => 'qris', 'va_numbers' => null]),
        ]);

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'pending', ['transaction_id' => 'tx-bca']))->assertOk();
        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement', ['transaction_id' => 'tx-qris']))->assertOk();

        $payment = $this->payment->fresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('tx-qris', $payment->transaction_id);
        $this->assertSame(['tx-bca'], $payment->meta['previous_transaction_ids']);
        $this->assertSame('e-wallet', $payment->method);
        $this->assertSame('qris', $payment->payment_type);
    }

    public function test_lunas_setelah_kadaluarsa_ditandai_paid_after_closed_dan_dijawab_200(): void
    {
        $this->travel(2)->hours();
        $this->fakeStatus($this->payment, [
            $this->midtransStatus($this->payment, 'pending'),
            $this->midtransStatus($this->payment, 'settlement'),
        ]);

        $this->artisan('checkouts:expire')->assertSuccessful();
        $this->assertSame(Checkout::STATUS_EXPIRED, $this->checkout->fresh()->status);

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))
            ->assertOk()
            ->assertJson(['message' => Payment::FLAG_PAID_AFTER_CLOSED]);

        $this->assertSame(Checkout::STATUS_EXPIRED, $this->checkout->fresh()->status);
        $this->assertSame(Payment::FLAG_PAID_AFTER_CLOSED, $this->payment->fresh()->review_flag);
        $this->assertSame(0, $this->paidLogs());
    }

    public function test_tagihan_snap_gagal_dibuat_lalu_dibayar_setelah_transaksi_dibatalkan(): void
    {
        // Revisi (c): Snap sempat terbentuk di Midtrans walau sistem mencatatnya gagal; transaksi lalu dibatalkan
        $this->payment->update(['status' => Payment::STATUS_FAILED, 'meta' => ['closed_reason' => 'snap_create_failed']]);
        $this->actingAs($this->pelanggan)->post(route('pelanggan.pembayaran.cancel', $this->checkout))->assertSessionHas('status');
        $this->assertSame(Checkout::STATUS_CANCELLED, $this->checkout->fresh()->status);

        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));
        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))->assertOk();

        $this->assertSame(Checkout::STATUS_CANCELLED, $this->checkout->fresh()->status);
        $this->assertSame(Payment::FLAG_PAID_AFTER_CLOSED, $this->payment->fresh()->review_flag);
    }

    public function test_tagihan_yang_ditutup_sistem_tetapi_dibayar_saat_transaksi_masih_menunggu_diterima(): void
    {
        // Asumsi: transaksi masih menunggu pembayaran dengan nominal sama → uang diterima, transaksi lunas
        $this->payment->update(['status' => Payment::STATUS_FAILED, 'meta' => ['closed_reason' => 'stale_in_flight']]);
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))->assertOk();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $this->payment->fresh()->meta['accepted_after_closed']);
    }

    public function test_deny_expire_dan_cancel_menutup_transaksi_yang_masih_menunggu(): void
    {
        foreach (['deny' => Checkout::STATUS_FAILED, 'expire' => Checkout::STATUS_EXPIRED, 'cancel' => Checkout::STATUS_CANCELLED] as $remote => $expected) {
            $checkout = $this->makeCartCheckout($this->pelanggan);
            $payment = $this->openSnap($checkout, $this->pelanggan, "snap-{$remote}");
            $this->fakeStatus($payment, $this->midtransStatus($payment, $remote));

            $this->sendMidtransWebhook($this->midtransStatus($payment, $remote))->assertOk();

            $this->assertSame($expected, $checkout->fresh()->status, $remote);
            $this->assertNotSame(Payment::STATUS_PENDING, $payment->fresh()->status, $remote);
            $this->assertSame(OrderStateMachine::ACTOR_WEBHOOK, $checkout->statusLogs()->get()->last()->actor_type);
        }
    }

    public function test_capture_challenge_belum_lunas_capture_accept_lunas(): void
    {
        $this->fakeStatus($this->payment, [
            $this->midtransStatus($this->payment, 'capture', ['fraud_status' => 'challenge', 'payment_type' => 'credit_card']),
            $this->midtransStatus($this->payment, 'capture', ['fraud_status' => 'accept', 'payment_type' => 'credit_card']),
        ]);

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'capture'))->assertOk();
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'capture'))->assertOk();
        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);

        $this->assertEqualsCanonicalizing(
            ["tx-{$this->payment->reference}-a:capture:challenge", "tx-{$this->payment->reference}-a:capture:accept"],
            PaymentEvent::pluck('event_id')->all(),
        );
    }

    public function test_reference_tidak_dikenal_dijawab_200_tanpa_perubahan(): void
    {
        $payload = $this->midtransStatus($this->payment, 'settlement', ['order_id' => 'TRX-TIDAK-ADA-P1']);

        $this->sendMidtransWebhook($payload)->assertOk()->assertJson(['message' => MidtransNotificationService::RESULT_UNKNOWN_REFERENCE]);
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    public function test_api_status_tidak_bisa_dihubungi_dijawab_503_agar_dikirim_ulang(): void
    {
        $this->midtransFakes[self::API_URL . $this->payment->reference . '/status'] = Http::response(['status_message' => 'down'], 500);
        $this->fakeMidtrans();

        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))->assertStatus(503);

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
        $this->assertSame(0, PaymentEvent::count());
    }

    // ── Rekonsiliasi & kedaluwarsa ──────────────────────────────────

    public function test_rekonsiliasi_menangkap_pembayaran_saat_webhook_hilang(): void
    {
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $this->assertSame(OrderStateMachine::ACTOR_RECONCILE, $this->checkout->statusLogs()->where('to_status', Checkout::STATUS_PAID)->sole()->actor_type);

        // Webhook yang datang terlambat setelah rekonsiliasi: kejadian sama, tidak diproses dua kali
        $this->sendMidtransWebhook($this->midtransStatus($this->payment, 'settlement'))->assertOk()->assertJson(['message' => 'duplicate']);
        $this->assertSame(1, $this->paidLogs());
    }

    public function test_kedaluwarsa_menunggu_masa_tenggang_lalu_menutup_tagihan_di_midtrans(): void
    {
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'pending'));

        // Batas bayar baru lewat 3 menit: masih dalam masa tenggang 5 menit
        $this->travelTo($this->checkout->payment_deadline->copy()->addMinutes(3));
        $this->artisan('checkouts:expire')->assertSuccessful();
        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);

        $this->travelTo($this->checkout->payment_deadline->copy()->addMinutes(6));
        $this->artisan('checkouts:expire')->assertSuccessful();

        $checkout = $this->checkout->fresh();
        $this->assertSame(Checkout::STATUS_EXPIRED, $checkout->status);
        $this->assertSame('kedaluwarsa', $checkout->pending_reason);
        $this->assertSame(Payment::STATUS_EXPIRED, $this->payment->fresh()->status);
        $this->assertSame(OrderStateMachine::ACTOR_SCHEDULER, $checkout->statusLogs()->get()->last()->actor_type);
        Http::assertSent(fn (Request $r) => $r->url() === self::API_URL . $this->payment->reference . '/expire' && $r->method() === 'POST');
    }

    public function test_kedaluwarsa_rekonsiliasi_dulu_jika_ternyata_sudah_dibayar(): void
    {
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));
        $this->travel(2)->hours();

        $this->artisan('checkouts:expire')->assertSuccessful();

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/expire'));
    }

    public function test_kedaluwarsa_dilewati_jika_status_midtrans_tidak_bisa_dicek(): void
    {
        $this->midtransFakes[self::API_URL . $this->payment->reference . '/status'] = Http::response(['status_message' => 'down'], 500);
        $this->fakeMidtrans();
        $this->travel(2)->hours();

        $this->artisan('checkouts:expire')->assertSuccessful();

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    public function test_kedaluwarsa_memakai_created_at_jika_batas_bayar_kosong(): void
    {
        $legacy = $this->makeCartCheckout($this->pelanggan);
        $legacy->forceFill(['payment_deadline' => null])->save();

        // created_at + 60 menit + tenggang 5 menit belum lewat
        $this->travel(62)->minutes();
        $this->artisan('checkouts:expire');
        $this->assertSame(Checkout::STATUS_AWAITING, $legacy->fresh()->status);

        $this->travel(5)->minutes();
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'pending'));
        $this->artisan('checkouts:expire');
        $this->assertSame(Checkout::STATUS_EXPIRED, $legacy->fresh()->status);
    }

    public function test_job_kedaluwarsa_tidak_berjalan_saat_flag_mati(): void
    {
        config(['payment.enabled' => false]);
        $this->travel(2)->hours();

        $this->artisan('checkouts:expire')->assertSuccessful();

        $this->assertSame(Checkout::STATUS_AWAITING, $this->checkout->fresh()->status);
    }

    public function test_jadwal_scheduler_terdaftar_tanpa_tumpang_tindih(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        foreach (['payments:reconcile', 'checkouts:expire'] as $command) {
            $event = $events->first(fn ($e) => str_contains($e->command, $command));
            $this->assertNotNull($event, $command);
            $this->assertSame('*/5 * * * *', $event->expression);
            $this->assertTrue($event->withoutOverlapping);
        }
    }

    // ── Admin: cek status & Perlu Refund ────────────────────────────

    public function test_admin_cek_status_ke_midtrans_memakai_jalur_webhook(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);
        $order = $this->checkout->orders()->first();
        $this->fakeStatus($this->payment, $this->midtransStatus($this->payment, 'settlement'));

        $this->actingAs($admin)->post(route('admin.orders.check-midtrans', $order))->assertSessionHas('success');

        $this->assertSame(Checkout::STATUS_PAID, $this->checkout->fresh()->status);
        $log = $this->checkout->statusLogs()->where('to_status', Checkout::STATUS_PAID)->sole();
        $this->assertSame([OrderStateMachine::ACTOR_RECONCILE, $admin->id], [$log->actor_type, $log->actor_id]);
    }

    public function test_hanya_admin_yang_bisa_cek_status_dan_membuka_perlu_refund(): void
    {
        $order = $this->checkout->orders()->first();

        foreach ([$this->makeUser(UserRole::PEGAWAI), $this->pelanggan] as $user) {
            $this->actingAs($user)->post(route('admin.orders.check-midtrans', $order))->assertForbidden();
            $this->actingAs($user)->get(route('admin.refunds.index'))->assertForbidden();
        }

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/status'));
    }

    public function test_perlu_refund_menampilkan_pembayaran_bermasalah_dan_menandai_dengan_catatan(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);
        $status = $this->midtransStatus($this->payment, 'settlement', ['gross_amount' => '1000.00']);
        $this->fakeStatus($this->payment, $status);
        $this->sendMidtransWebhook($status)->assertOk();

        $this->actingAs($admin)->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee($this->payment->reference)
            ->assertSee('Nominal tidak sesuai');

        $this->actingAs($admin)->post(route('admin.refunds.mark', $this->payment), ['refund_note' => ''])
            ->assertSessionHasErrors('refund_note');
        $this->assertNull($this->payment->fresh()->refunded_at);

        $this->actingAs($admin)->post(route('admin.refunds.mark', $this->payment), ['refund_note' => 'Refund Midtrans #RF-001'])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.refunds.mark', $this->payment), ['refund_note' => 'klik kedua'])
            ->assertSessionHas('status');

        $payment = $this->payment->fresh();
        $this->assertSame('Refund Midtrans #RF-001', $payment->refund_note);
        $this->assertSame($admin->id, $payment->refunded_by);
        $this->assertNotNull($payment->refunded_at);

        $this->actingAs($admin)->get(route('admin.refunds.index'))->assertViewHas('payments', fn ($p) => $p->isEmpty());
        $this->actingAs($admin)->get(route('admin.refunds.index', ['tab' => 'selesai']))->assertSee('Refund Midtrans #RF-001');
    }

    public function test_pembayaran_tanpa_flag_tidak_bisa_ditandai_refund(): void
    {
        $admin = $this->makeUser(UserRole::ADMIN);

        $this->actingAs($admin)->post(route('admin.refunds.mark', $this->payment), ['refund_note' => 'coba-coba'])
            ->assertSessionHas('error');
        $this->assertNull($this->payment->fresh()->refunded_at);
    }
}
