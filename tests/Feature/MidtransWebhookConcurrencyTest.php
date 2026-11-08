<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentNotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\MidtransFakes;
use Tests\TestCase;

/**
 * Webhook settlement yang sama dikirim 3x BENAR-BENAR paralel (3 proses PHP terpisah ke database testing):
 * transaksi lunas 1x, 1 log status, 1 payment_event. Dijaga oleh lock baris checkout → payment dan
 * UNIQUE payment_events(gateway, event_id).
 *
 * Memakai DatabaseTruncation (bukan RefreshDatabase) karena proses lain hanya melihat data yang sudah di-commit;
 * tabel dikosongkan lagi setelah test agar tidak mengganggu test lain.
 */
class MidtransWebhookConcurrencyTest extends TestCase
{
    use DatabaseTruncation, CreatesLaundryData, MidtransFakes;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_settlement_dikirim_tiga_kali_paralel_lunas_sekali_dan_satu_log(): void
    {
        $this->enableMidtrans();

        /** @var User $pelanggan */
        $pelanggan = $this->makeUser(attributes: ['phone' => '081234567890']);
        $checkout = $this->makeCartCheckout($pelanggan);
        $payment = $this->openSnap($checkout, $pelanggan);

        $status = $this->midtransStatus($payment, 'settlement');
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'midtrans-paralel-' . $payment->id . '-' . getmypid();
        @mkdir($dir);
        file_put_contents("{$dir}/body.json", $this->signedBody($status));
        file_put_contents("{$dir}/status.json", json_encode($status));

        $startAt = (string) (microtime(true) + 4);
        $env = [
            'APP_ENV'             => 'testing',
            'DB_CONNECTION'       => 'mysql',
            'DB_DATABASE'         => config('database.connections.mysql.database'),
            'PAYMENT_ENABLED'     => 'true',
            'MIDTRANS_SERVER_KEY' => config('payment.gateways.midtrans.server_key'),
            'CACHE_STORE'         => 'array',
            'SESSION_DRIVER'      => 'array',
            'QUEUE_CONNECTION'    => 'sync',
            'LOG_CHANNEL'         => 'null',
        ];

        $workers = collect(range(1, 3))->map(function () use ($dir, $startAt, $env) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/midtrans_webhook_worker.php'), "{$dir}/body.json", "{$dir}/status.json", $startAt], base_path(), $env, null, 60);
            $process->start();

            return $process;
        });

        $responses = $workers->map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());

            return json_decode($process->getOutput(), true);
        });

        @unlink("{$dir}/body.json");
        @unlink("{$dir}/status.json");
        @rmdir($dir);

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
