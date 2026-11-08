<?php

namespace App\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\InvalidPaymentNotification;
use App\Payments\PaymentCharge;
use App\Payments\PaymentNotification;
use App\Support\Rupiah;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Midtrans Snap lewat HTTP client Laravel (tanpa SDK).
 *
 * - createCharge: POST /snap/v1/transactions. order_id = payments.reference ({kode}-P{n}) yang sudah dibuat
 *   PaymentService; Midtrans menolak order_id ganda, jadi percobaan ulang selalu memakai nomor baru.
 * - status / expire: Core API /v2/{order_id}/… (konfirmasi status, pembatalan tagihan pending).
 * - gross_amount selalu integer rupiah dari payments.amount (= total transaksi di server).
 */
class MidtransGateway implements PaymentGateway
{
    public const NAME = 'midtrans';

    public function name(): string
    {
        return self::NAME;
    }

    public function createCharge(Payment $payment, string $idempotencyKey): PaymentCharge
    {
        $checkout = $payment->checkout()->with(['orders.service', 'user'])->firstOrFail();
        $grossAmount = Rupiah::parse($payment->amount);

        if ($grossAmount !== $checkout->totalRupiah()) {
            throw new RuntimeException("Nominal tagihan {$payment->reference} tidak sama dengan total transaksi {$checkout->code}.");
        }

        $payload = [
            'transaction_details' => [
                'order_id'     => $payment->reference,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => Str::limit((string) $checkout->user?->name, 255, ''),
                'email'      => $checkout->user?->email,
                'phone'      => $checkout->contact_phone,
            ],
            'enabled_payments' => config('payment.gateways.midtrans.enabled_payments'),
            'expiry' => [
                'start_time' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                'unit'       => 'minute',
                'duration'   => $this->expiryMinutes($payment),
            ],
            'callbacks' => [
                // Halaman "sedang memverifikasi" (polling); halaman ini tidak pernah mengubah status
                'finish' => route('pelanggan.pembayaran.show', ['checkout' => $checkout, 'verifikasi' => 1]),
            ],
        ];

        $items = $this->itemDetails($checkout->orders);

        // Midtrans menolak jika jumlah item ≠ gross_amount; tanpa rincian lebih baik daripada gagal bayar
        if (array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items)) === $grossAmount) {
            $payload['item_details'] = $items;
        } else {
            Log::warning('midtrans.item_details_skipped', ['checkout_code' => $checkout->code, 'reference' => $payment->reference]);
        }

        $response = $this->client()->post($this->snapBaseUrl() . '/snap/v1/transactions', $payload);

        if (! $response->successful() || ! $response->json('token')) {
            throw new RuntimeException('Midtrans menolak pembuatan tagihan: ' . $this->errorMessage($response));
        }

        return new PaymentCharge(
            reference: $payment->reference,
            checkoutUrl: (string) $response->json('redirect_url'),
            expiresAt: $payment->expires_at,
            meta: ['snap_token' => (string) $response->json('token')],
        );
    }

    /**
     * Status transaksi dari Core API. null = Midtrans belum punya transaksi untuk order_id ini
     * (Snap sudah dibuat tetapi pelanggan belum memilih metode bayar).
     *
     * @return array<string, mixed>|null
     */
    public function status(string $orderId): ?array
    {
        $response = $this->client()->get($this->apiBaseUrl() . '/v2/' . rawurlencode($orderId) . '/status');

        if ($this->isNotFound($response)) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengecek status ke Midtrans: ' . $this->errorMessage($response));
        }

        return $response->json();
    }

    /**
     * Menutup tagihan pending di Midtrans agar tidak bisa dibayar lagi. Tagihan yang belum punya transaksi
     * (404) dianggap sudah tertutup; Snap token-nya kedaluwarsa sendiri sesuai expiry.
     */
    public function expire(string $orderId): void
    {
        $response = $this->client()->post($this->apiBaseUrl() . '/v2/' . rawurlencode($orderId) . '/expire');

        if ($this->isNotFound($response)) {
            return;
        }

        if (! $response->successful() || ! in_array((string) $response->json('status_code'), ['200', '407'], true)) {
            throw new RuntimeException('Gagal menutup tagihan di Midtrans: ' . $this->errorMessage($response));
        }
    }

    public function parseNotification(string $rawBody, array $headers): PaymentNotification
    {
        // Diaktifkan di Tahap 5 (verifikasi signature + konfirmasi status + dedup per transaction_id & status)
        throw new InvalidPaymentNotification('Notifikasi Midtrans belum diproses (Tahap 5).');
    }

    public static function clientKey(): ?string
    {
        return config('payment.gateways.midtrans.client_key');
    }

    public static function snapJsUrl(): string
    {
        return (config('payment.gateways.midtrans.is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com') . '/snap/snap.js';
    }

    // ─────────────────────────────────────────────────────────────

    protected function client(): PendingRequest
    {
        $serverKey = (string) config('payment.gateways.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di .env.');
        }

        return Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('payment.gateways.midtrans.timeout', 15));
    }

    protected function snapBaseUrl(): string
    {
        return config('payment.gateways.midtrans.is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    protected function apiBaseUrl(): string
    {
        return config('payment.gateways.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    /**
     * Sisa menit sampai batas bayar transaksi (Snap kedaluwarsa bersamaan dengan transaksinya).
     */
    protected function expiryMinutes(Payment $payment): int
    {
        if (! $payment->expires_at) {
            return (int) config('payment.expiry_minutes', 60);
        }

        return max(1, (int) ceil(now()->diffInSeconds($payment->expires_at, false) / 60));
    }

    /**
     * @return list<array{id: string, price: int, quantity: int, name: string}>
     */
    protected function itemDetails($orders): array
    {
        return $orders->map(fn (Order $order) => [
            'id'       => $order->order_code,
            'price'    => Rupiah::parse($order->unit_price),
            'quantity' => (int) $order->machine_qty,
            // Batas nama item Midtrans 50 karakter
            'name'     => Str::limit($order->serviceName(), 50, ''),
        ])->values()->all();
    }

    protected function isNotFound(Response $response): bool
    {
        return $response->status() === 404 || (string) $response->json('status_code') === '404';
    }

    protected function errorMessage(Response $response): string
    {
        $messages = $response->json('error_messages') ?? [$response->json('status_message') ?? ('HTTP ' . $response->status())];

        return implode('; ', (array) $messages);
    }
}
