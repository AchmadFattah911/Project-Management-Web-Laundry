<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentNotificationLog;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\MidtransFakes;
use Tests\Concerns\RunsParallelWorkers;
use Tests\TestCase;

/**
 * Tes wajib #4: webhook settlement yang sama dikirim 3x BENAR-BENAR paralel (3 proses PHP terpisah ke
 * database testing) → transaksi lunas 1x, 1 log status, 1 payment_event. Dijaga oleh lock baris
 * checkout → payment dan UNIQUE payment_events(gateway, event_id).
 */
class MidtransWebhookConcurrencyTest extends TestCase
{
    use DatabaseTruncation, CreatesLaundryData, MidtransFakes, RunsParallelWorkers;

    public function test_settlement_dikirim_tiga_kali_paralel_lunas_sekali_dan_satu_log(): void
    {
        $this->enableMidtrans();

        $pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $checkout = $this->makeCartCheckout($pelanggan);
        $payment = $this->openSnap($checkout, $pelanggan);

        $status = $this->midtransStatus($payment, 'settlement');
        $body = tempnam(sys_get_temp_dir(), 'mt-body');
        $statusFile = tempnam(sys_get_temp_dir(), 'mt-status');
        file_put_contents($body, $this->signedBody($status));
        file_put_contents($statusFile, json_encode($status));

        try {
            $responses = $this->runParallelWorkers('midtrans_webhook_worker.php', array_fill(0, 3, [$body, $statusFile]), [
                'PAYMENT_ENABLED'     => 'true',
                'MIDTRANS_SERVER_KEY' => config('payment.gateways.midtrans.server_key'),
            ]);
        } finally {
            @unlink($body);
            @unlink($statusFile);
        }

        $this->assertSame([200, 200, 200], $responses->pluck('http')->all());

        $results = $responses->map(fn ($r) => json_decode($r['body'], true)['message'])->sort()->values()->all();
        $this->assertSame(['applied', 'duplicate', 'duplicate'], $results);

        $this->assertSame(Checkout::STATUS_PAID, $checkout->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(1, $checkout->statusLogs()->where('to_status', Checkout::STATUS_PAID)->count());
        $this->assertSame(1, PaymentEvent::where('gateway', 'midtrans')->count());
        $this->assertSame(3, PaymentNotificationLog::count());
    }
}
