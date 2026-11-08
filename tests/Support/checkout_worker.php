<?php

/**
 * Worker untuk CheckoutConcurrencyTest: satu proses PHP terpisah = satu submit form checkout
 * dari pelanggan yang sama. Beberapa worker menunggu waktu mulai yang sama agar benar-benar paralel.
 *
 * Argumen: <user id> <file JSON isi form> <waktu mulai (microtime float)>
 * Output : JSON {"http": int, "location": string|null}
 */

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

[$script, $userId, $formFile, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Http::preventStrayRequests();

$form = json_decode(file_get_contents($formFile), true);
$request = Request::create('/pesan/checkout', 'POST', $form, [], [], ['REMOTE_ADDR' => '127.0.0.1']);

// Sesi login pelanggan (CSRF dilewati otomatis karena APP_ENV=testing di CLI)
$app->instance('request', $request);
Auth::guard('web')->setUser(User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

$response = $kernel->handle($request);

echo json_encode(['http' => $response->getStatusCode(), 'location' => $response->headers->get('Location')]);
