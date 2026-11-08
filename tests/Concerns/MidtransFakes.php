<?php

namespace Tests\Concerns;

use App\Models\Checkout;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Bantuan test Midtrans: transaksi keranjang + tagihan Snap, notifikasi bertanda tangan, dan API status palsu.
 * Tidak ada request jaringan sungguhan (Http::fake).
 */
trait MidtransFakes
{
    protected const SNAP_URL = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    protected const API_URL = 'https://api.sandbox.midtrans.com/v2/';

    /** @var array<string, mixed> respons palsu per URL; dipasang ulang setiap fakeMidtrans() */
    protected array $midtransFakes = [];

    protected function enableMidtrans(): void
    {
        config(['payment.enabled' => true, 'payment.default' => 'midtrans', 'payment.expiry_minutes' => 60]);
    }

    protected function makeCartCheckout(User $pelanggan): Checkout
    {
        $suffix = Str::lower(Str::random(5));
        $lipat = $this->makeService(attributes: ['name' => 'Cuci Lipat Uji ' . $suffix, 'price' => 35000]);
        $saja = $this->makeService(attributes: ['name' => 'Cuci Saja Uji ' . $suffix, 'price' => 15000]);

        $this->actingAs($pelanggan)->post(route('pelanggan.cart.store'), ['items' => [
            ['service_id' => $lipat->id, 'qty' => 2, 'item_type' => 'sprei'],
            ['service_id' => $saja->id, 'qty' => 1, 'item_type' => 'pakaian'],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($pelanggan)->post(route('pelanggan.checkout.store'), [
            'cart_items'       => $pelanggan->cartItems()->pluck('id')->all(),
            'drop_off_date'    => now()->addDay()->toDateString(),
            'agree'            => '1',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Checkout::where('user_id', $pelanggan->id)->latest('id')->firstOrFail();
    }

    /**
     * Pelanggan menekan "Bayar Sekarang" → tagihan Snap {kode}-P{n}.
     */
    protected function openSnap(Checkout $checkout, User $pelanggan, string $token = 'snap-token-1'): Payment
    {
        $this->midtransFakes[self::SNAP_URL] = Http::response(['token' => $token, 'redirect_url' => "https://app.sandbox.midtrans.com/snap/v4/redirection/{$token}"], 201);
        $this->fakeMidtrans();

        $this->actingAs($pelanggan)->postJson(route('pelanggan.pembayaran.pay', $checkout))->assertSuccessful();

        return $checkout->payments()->latest('id')->firstOrFail();
    }

    /**
     * Respons API status Midtrans (GET /v2/{order_id}/status) = sumber kebenaran.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $statuses  satu status, atau urutan status per panggilan
     */
    protected function fakeStatus(Payment $payment, array $statuses): void
    {
        $statuses = array_is_list($statuses) ? $statuses : [$statuses];
        $sequence = Http::sequence();

        foreach ($statuses as $status) {
            $sequence->push($status, (int) ($status['status_code'] ?? 200) === 404 ? 404 : 200);
        }

        $this->midtransFakes[self::API_URL . $payment->reference . '/status'] = $sequence->whenEmpty(Http::response(end($statuses)));
        $this->midtransFakes[self::API_URL . $payment->reference . '/expire'] = Http::response(['status_code' => '407', 'transaction_status' => 'expire']);
        $this->fakeMidtrans();
    }

    protected bool $midtransFaked = false;

    /**
     * Satu callback yang membaca $midtransFakes saat request dikirim, sehingga stub bisa diganti di tengah test
     * (Http::fake biasa memakai stub yang terdaftar pertama). URL yang tidak dipalsukan dijawab 500.
     */
    protected function fakeMidtrans(): void
    {
        if ($this->midtransFaked) {
            return;
        }

        $this->midtransFaked = true;

        Http::fake(function (Request $request) {
            $url = strtok($request->url(), '?');
            $stub = $this->midtransFakes[$url] ?? null;

            if ($stub === null) {
                return Http::response(['status_message' => "Tidak dipalsukan: {$url}"], 500);
            }

            return is_callable($stub) ? $stub($request) : $stub;
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function midtransStatus(Payment $payment, string $transactionStatus, array $overrides = []): array
    {
        $statusCode = match ($transactionStatus) {
            'settlement', 'capture' => '200',
            'pending'               => '201',
            'deny'                  => '202',
            'expire'                => '407',
            default                 => '200',
        };

        return array_merge([
            'status_code'        => $statusCode,
            'transaction_id'     => 'tx-' . $payment->reference . '-a',
            'order_id'           => $payment->reference,
            'gross_amount'       => number_format((float) $payment->amount, 2, '.', ''),
            'currency'           => 'IDR',
            'payment_type'       => 'bank_transfer',
            'transaction_status' => $transactionStatus,
            'fraud_status'       => 'accept',
            'transaction_time'   => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'settlement_time'    => $transactionStatus === 'settlement' ? now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') : null,
            'va_numbers'         => [['bank' => 'bca', 'va_number' => '12345678901']],
            'expiry_time'        => now()->addHour()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * Notifikasi webhook bertanda tangan (signature_key = SHA512(order_id + status_code + gross_amount + server_key)).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function signedBody(array $payload, ?string $signature = null): string
    {
        $payload['signature_key'] = $signature ?? hash('sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . config('payment.gateways.midtrans.server_key'));

        return json_encode($payload);
    }

    protected function sendMidtransWebhook(array $payload, ?string $signature = null): TestResponse
    {
        return $this->call('POST', route('payments.webhook', 'midtrans'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->signedBody($payload, $signature));
    }
}
