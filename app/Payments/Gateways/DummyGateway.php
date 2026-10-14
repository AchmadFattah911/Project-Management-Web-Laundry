<?php

namespace App\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Payments\InvalidPaymentNotification;
use App\Payments\PaymentCharge;
use App\Payments\PaymentNotification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gateway palsu untuk development & testing.
 *
 * Meniru alur gateway asli: membuat tagihan dengan reference unik dan halaman checkout,
 * lalu status dikirim balik lewat notifikasi bertanda tangan HMAC (seperti webhook asli).
 * Tidak ada uang sungguhan yang berpindah.
 *
 * Perilaku idempotency juga ditiru: idempotency key yang sama selalu menghasilkan reference yang sama,
 * seperti gateway asli yang mengembalikan tagihan lama untuk key yang sudah pernah dipakai.
 */
class DummyGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'x-dummy-signature';

    public function __construct(
        protected string $secret,
        protected int $expiryMinutes = 60,
        protected int $failRate = 0,
    ) {}

    public function name(): string
    {
        return 'dummy';
    }

    public function createCharge(Payment $payment, string $idempotencyKey): PaymentCharge
    {
        // Simulasi gangguan jaringan/gateway untuk menguji retry dengan key yang sama
        if ($this->failRate > 0 && random_int(1, 100) <= $this->failRate) {
            throw new RuntimeException('Simulasi: gateway dummy tidak merespons.');
        }

        $reference = 'DUMMY-' . strtoupper(substr(hash('sha256', $idempotencyKey), 0, 12));

        return new PaymentCharge(
            reference: $reference,
            checkoutUrl: route('payments.dummy.show', $reference),
            expiresAt: now()->addMinutes($this->expiryMinutes),
            meta: ['note' => 'Transaksi simulasi, tidak ada dana sungguhan.'],
        );
    }

    public function parseNotification(string $rawBody, array $headers): PaymentNotification
    {
        $signature = (string) ($headers[self::SIGNATURE_HEADER] ?? '');

        if ($signature === '' || ! hash_equals($this->sign($rawBody), $signature)) {
            throw new InvalidPaymentNotification('Signature notifikasi pembayaran tidak valid.');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload) || empty($payload['reference']) || ! isset($payload['status'], $payload['amount'])) {
            throw new InvalidPaymentNotification('Payload notifikasi pembayaran tidak lengkap.');
        }

        return new PaymentNotification(
            reference: (string) $payload['reference'],
            status: (string) $payload['status'],
            amountInCents: (int) round(((float) $payload['amount']) * 100),
            raw: $payload,
            eventId: isset($payload['event_id']) ? (string) $payload['event_id'] : null,
        );
    }

    /**
     * Membuat body + signature seperti yang akan dikirim gateway asli ke webhook.
     * Dipakai halaman simulasi pembayaran untuk tester.
     *
     * @return array{body: string, headers: array<string, string>}
     */
    public function buildNotification(Payment $payment, string $status, ?string $eventId = null): array
    {
        $body = json_encode([
            'event_id'  => $eventId ?? 'EVT-' . strtoupper(Str::random(16)),
            'reference' => $payment->reference,
            'status'    => $status,
            'amount'    => (string) $payment->amount,
            'sent_at'   => now()->toIso8601String(),
        ]);

        return [
            'body'    => $body,
            'headers' => [self::SIGNATURE_HEADER => $this->sign($body)],
        ];
    }

    protected function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }
}
