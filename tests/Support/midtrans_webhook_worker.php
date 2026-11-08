<?php

/**
 * Worker untuk MidtransWebhookConcurrencyTest: satu proses PHP terpisah = satu request webhook.
 * Beberapa worker dijalankan bersamaan dan menunggu waktu mulai yang sama agar benar-benar paralel
 * terhadap database testing (data sudah di-commit oleh test).
 *
 * Argumen: <file body webhook> <file JSON status API palsu> <waktu mulai (microtime float)>
 * Output : JSON {"http": int, "body": string}
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

[$script, $bodyFile, $statusFile, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$status = json_decode(file_get_contents($statusFile), true);

// Tidak ada request jaringan sungguhan: API status Midtrans dipalsukan di proses ini juga
Http::preventStrayRequests();
Http::fake([
    'api.sandbox.midtrans.com/v2/*/status' => Http::response($status),
    'api.sandbox.midtrans.com/v2/*/expire' => Http::response(['status_code' => '407']),
]);

$body = file_get_contents($bodyFile);

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

$response = $kernel->handle(Request::create('/payments/webhook/midtrans', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR'  => '127.0.0.1',
], $body));

echo json_encode(['http' => $response->getStatusCode(), 'body' => $response->getContent()]);
