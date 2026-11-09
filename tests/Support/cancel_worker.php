<?php

/**
 * Worker untuk OnlineCancellationConcurrencyTest: satu proses PHP = satu klik "Batalkan Pesanan".
 *
 * Argumen: <user id> <kode transaksi> <waktu mulai (microtime float)>
 * Output : JSON {"http": int, "location": string|null}
 */

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

[$script, $userId, $code, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Http::preventStrayRequests();

$request = Request::create("/pelanggan/transaksi/{$code}/batal", 'POST', [
    'bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Pelanggan Uji',
], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

$app->instance('request', $request);
Auth::guard('web')->setUser(User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

$response = $kernel->handle($request);

echo json_encode(['http' => $response->getStatusCode(), 'location' => $response->headers->get('Location')]);
