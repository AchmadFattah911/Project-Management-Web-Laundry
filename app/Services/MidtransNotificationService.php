<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentNotificationLog;
use App\Models\User;
use App\Payments\Gateways\MidtransGateway;
use App\Support\Rupiah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Satu jalur untuk menerapkan status Midtrans ke database, dipakai oleh:
 * webhook, payments:reconcile, checkouts:expire, dan tombol admin "Cek status ke Midtrans".
 *
 * Webhook: simpan notifikasi mentah (selalu) → verifikasi signature (salah = 403) → konfirmasi ke
 * GET /v2/{order_id}/status (SUMBER KEBENARAN, bukan isi notifikasi) → terapkan dalam DB::transaction
 * dengan lock checkout → payment.
 *
 * Aturan saat menerapkan:
 * - dedup per {transaction_id}:{transaction_status}[:{fraud_status}] (UNIQUE payment_events) → kejadian yang
 *   sama hanya diterapkan sekali; pending lalu settlement dengan transaction_id sama = dua kejadian berbeda;
 * - nominal dibandingkan sebagai integer rupiah; beda → review_flag amount_mismatch, tidak lunas;
 * - lunas tidak pernah turun; pending tidak menurunkan apa pun;
 * - lunas untuk transaksi yang sudah ditutup → paid_after_closed; lunas kedua untuk transaksi yang sudah
 *   lunas → duplicate_paid_needs_refund. Status transaksi tidak diubah, uang tercatat untuk direfund.
 */
class MidtransNotificationService
{
    public const RESULT_APPLIED = 'applied';
    public const RESULT_NO_CHANGE = 'no_change';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_IGNORED = 'ignored';
    public const RESULT_INVALID_PAYLOAD = 'invalid_payload';
    public const RESULT_INVALID_SIGNATURE = 'invalid_signature';
    public const RESULT_UNKNOWN_REFERENCE = 'unknown_reference';
    public const RESULT_STATUS_UNAVAILABLE = 'status_unavailable';
    public const RESULT_NOT_FOUND_AT_GATEWAY = 'not_found_at_gateway';

    /** Batas ukuran body yang disimpan (notifikasi Midtrans normal < 4 KB). */
    protected const MAX_STORED_BODY = 65535;

    public function __construct(
        protected OrderStateMachine $stateMachine,
    ) {}

    public function gateway(): MidtransGateway
    {
        return app(MidtransGateway::class);
    }

    // ─────────────────────────────────────────────────────────────
    //  Webhook
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array{http: int, result: string}
     */
    public function handleWebhook(string $rawBody, ?string $ip = null): array
    {
        $payload = json_decode($rawBody, true);
        $payload = is_array($payload) ? $payload : null;

        $record = PaymentNotificationLog::create([
            'gateway'            => MidtransGateway::NAME,
            'order_id'           => $this->field($payload, 'order_id', 100),
            'transaction_id'     => $this->field($payload, 'transaction_id', 100),
            'transaction_status' => $this->field($payload, 'transaction_status', 30),
            'fraud_status'       => $this->field($payload, 'fraud_status', 30),
            'gross_amount'       => $this->field($payload, 'gross_amount', 30),
            'ip'                 => $ip,
            'raw_body'           => mb_strcut($rawBody, 0, self::MAX_STORED_BODY),
        ]);

        if (! $payload || empty($payload['order_id']) || ! is_string($payload['order_id'])) {
            return $this->finish($record, self::RESULT_INVALID_PAYLOAD, 400);
        }

        if (! $this->gateway()->verifySignature($payload)) {
            $record->update(['signature_valid' => false]);
            Log::warning('midtrans.webhook_invalid_signature', ['order_id' => $record->order_id, 'ip' => $ip]);

            return $this->finish($record, self::RESULT_INVALID_SIGNATURE, 403);
        }

        $record->update(['signature_valid' => true]);

        $payment = Payment::where('gateway', MidtransGateway::NAME)->where('reference', $payload['order_id'])->first();

        // Mis. notifikasi uji dari dashboard Midtrans / environment lain: dijawab 200 agar tidak dikirim ulang terus
        if (! $payment) {
            Log::warning('midtrans.webhook_unknown_reference', ['order_id' => $record->order_id]);

            return $this->finish($record, self::RESULT_UNKNOWN_REFERENCE, 200);
        }

        $record->update(['payment_id' => $payment->id]);

        try {
            $result = $this->sync($payment, OrderStateMachine::ACTOR_WEBHOOK)['result'];
        } catch (Throwable $e) {
            // API status tidak bisa dihubungi: jawab 5xx agar Midtrans mengirim ulang (rekonsiliasi juga akan menangkapnya)
            Log::error('midtrans.webhook_status_unavailable', ['order_id' => $record->order_id, 'error' => $e->getMessage()]);

            return $this->finish($record, self::RESULT_STATUS_UNAVAILABLE, 503);
        }

        return $this->finish($record, $result, 200);
    }

    // ─────────────────────────────────────────────────────────────
    //  Konfirmasi status (webhook, rekonsiliasi, kedaluwarsa, admin)
    // ─────────────────────────────────────────────────────────────

    /**
     * Ambil status terbaru dari API Midtrans lalu terapkan.
     *
     * @return array{result: string, status: array<string, mixed>|null}
     *
     * @throws RuntimeException  API Midtrans tidak bisa dihubungi / menjawab error
     */
    public function sync(Payment $payment, string $actorType, ?User $actor = null): array
    {
        $status = $this->gateway()->status($payment->reference);

        // Snap sudah dibuat tetapi pelanggan belum memilih metode: belum ada transaksi di Midtrans
        if ($status === null) {
            return ['result' => self::RESULT_NOT_FOUND_AT_GATEWAY, 'status' => null];
        }

        return ['result' => $this->apply($payment, $status, $actorType, $actor), 'status' => $status];
    }

    /**
     * Menerapkan status Midtrans (hasil API status) ke payment & transaksinya.
     */
    public function apply(Payment $payment, array $status, string $actorType, ?User $actor = null): string
    {
        if ((string) ($status['order_id'] ?? '') !== (string) $payment->reference) {
            throw new RuntimeException("Status Midtrans untuk order_id '" . ($status['order_id'] ?? '') . "' tidak cocok dengan {$payment->reference}.");
        }

        $eventId = MidtransGateway::eventId($status);
        $closeAtGateway = [];

        try {
            $result = DB::transaction(function () use ($payment, $status, $actorType, $actor, $eventId, &$closeAtGateway) {
                // Urutan lock sama dengan /bayar, batal, dan job kedaluwarsa: checkout → payment
                $owner = $this->lockOwner($payment);
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if (PaymentEvent::where('gateway', MidtransGateway::NAME)->where('event_id', $eventId)->exists()) {
                    return self::RESULT_DUPLICATE;
                }

                $this->recordGatewayDetails($locked, $status);

                $result = match (MidtransGateway::statusKind($status)) {
                    'paid'      => $this->applyPaid($owner, $locked, $status, $actorType, $actor, $closeAtGateway),
                    'pending'   => $this->applyPending($owner, $locked),
                    'failed'    => $this->applyClosed($owner, $locked, $status, Payment::STATUS_FAILED, Checkout::STATUS_FAILED, 'ditolak', $actorType, $actor),
                    'expired'   => $this->applyClosed($owner, $locked, $status, Payment::STATUS_EXPIRED, Checkout::STATUS_EXPIRED, 'kedaluwarsa', $actorType, $actor),
                    'cancelled' => $this->applyClosed($owner, $locked, $status, Payment::STATUS_EXPIRED, Checkout::STATUS_CANCELLED, null, $actorType, $actor),
                    default     => self::RESULT_IGNORED,
                };

                PaymentEvent::create([
                    'gateway'    => MidtransGateway::NAME,
                    'event_id'   => $eventId,
                    'payment_id' => $locked->id,
                    'status'     => mb_substr((string) ($status['transaction_status'] ?? 'unknown'), 0, 20),
                    'payload'    => $status,
                ]);

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            // Kejadian yang sama sedang/sudah diterapkan proses lain (pengaman terakhir di database)
            return self::RESULT_DUPLICATE;
        }

        Log::info('midtrans.status_applied', [
            'reference'          => $payment->reference,
            'transaction_id'     => $status['transaction_id'] ?? null,
            'transaction_status' => $status['transaction_status'] ?? null,
            'actor_type'         => $actorType,
            'result'             => $result,
        ]);

        // Tagihan lain yang ditutup karena transaksi sudah lunas: tutup juga di Midtrans (di luar lock, best effort)
        foreach ($closeAtGateway as $reference) {
            try {
                $this->gateway()->expire($reference);
            } catch (Throwable $e) {
                Log::warning('midtrans.expire_other_failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $closeAtGateway  diisi reference tagihan lain yang perlu ditutup di Midtrans
     */
    protected function applyPaid(Checkout|Order|null $owner, Payment $payment, array $status, string $actorType, ?User $actor, array &$closeAtGateway): string
    {
        $gross = $this->rupiah($status['gross_amount'] ?? null);

        if ($payment->isPaid()) {
            // Order_id yang sama dilunasi dengan transaction_id lain (dibayar dua kali): catat untuk refund
            if (! empty($status['transaction_id']) && $status['transaction_id'] !== $payment->transaction_id) {
                return $this->flag($payment, Payment::FLAG_DUPLICATE_PAID, $status, $gross);
            }

            return self::RESULT_NO_CHANGE;
        }

        if ($gross === null || $gross !== Rupiah::parse($payment->amount)) {
            return $this->flag($payment, Payment::FLAG_AMOUNT_MISMATCH, $status, $gross);
        }

        if ($owner?->isPaid()) {
            return $this->flag($payment, Payment::FLAG_DUPLICATE_PAID, $status, $gross);
        }

        if ($owner instanceof Checkout && ! $owner->isAwaitingPayment()) {
            return $this->flag($payment, Payment::FLAG_PAID_AFTER_CLOSED, $status, $gross);
        }

        $meta = $payment->meta ?? [];

        // Tagihan ini sempat ditutup di sistem (mis. dianggap macet) tetapi transaksinya masih menunggu
        // pembayaran dengan nominal yang sama: uangnya diterima, transaksi menjadi lunas.
        if ($payment->status !== Payment::STATUS_PENDING) {
            $meta['accepted_after_closed'] = $payment->status;
        }

        $payment->update([
            'status'  => Payment::STATUS_PAID,
            'paid_at' => $this->gatewayTime($status['settlement_time'] ?? null) ?? now(),
            'meta'    => $meta,
        ]);

        if ($owner instanceof Checkout) {
            $this->stateMachine->transitionCheckout($owner, Checkout::STATUS_PAID, $actorType, $actor,
                "Lunas via Midtrans ({$payment->reference}, " . ($status['payment_type'] ?? '-') . ')');
        }

        // Tagihan lain yang masih terbuka ditutup agar tidak dibayar dua kali
        $others = Payment::where($payment->checkout_id ? 'checkout_id' : 'order_id', $payment->checkout_id ?: $payment->order_id)
            ->where('status', Payment::STATUS_PENDING)
            ->whereKeyNot($payment->id)
            ->lockForUpdate()
            ->get();

        foreach ($others as $other) {
            $other->update([
                'status' => Payment::STATUS_EXPIRED,
                'meta'   => array_merge($other->meta ?? [], ['closed_reason' => 'paid_by_other', 'closed_at' => now()->toIso8601String()]),
            ]);

            if ($other->gateway === MidtransGateway::NAME && $other->snap_token) {
                $closeAtGateway[] = $other->reference;
            }
        }

        return self::RESULT_APPLIED;
    }

    protected function applyPending(Checkout|Order|null $owner, Payment $payment): string
    {
        // pending tidak pernah menurunkan status (mis. pending yang datang terlambat setelah settlement)
        if ($payment->status !== Payment::STATUS_PENDING || ! $owner instanceof Checkout || ! $owner->isAwaitingPayment()) {
            return self::RESULT_NO_CHANGE;
        }

        if ($owner->pending_reason !== 'menunggu_transfer') {
            $owner->update(['pending_reason' => 'menunggu_transfer']);
        }

        return self::RESULT_APPLIED;
    }

    protected function applyClosed(Checkout|Order|null $owner, Payment $payment, array $status, string $paymentStatus, string $checkoutStatus, ?string $pendingReason, string $actorType, ?User $actor): string
    {
        // Hanya tagihan yang masih aktif yang ditutup; lunas tidak pernah turun
        if ($payment->status !== Payment::STATUS_PENDING) {
            return self::RESULT_NO_CHANGE;
        }

        $payment->update([
            'status' => $paymentStatus,
            'meta'   => array_merge($payment->meta ?? [], ['closed_reason' => 'midtrans_' . ($status['transaction_status'] ?? 'unknown'), 'closed_at' => now()->toIso8601String()]),
        ]);

        // Hanya jika transaksi masih menunggu (UNIQUE pending_owner: ini satu-satunya tagihan aktifnya)
        if ($owner instanceof Checkout && $owner->isAwaitingPayment()) {
            $this->stateMachine->transitionCheckout($owner, $checkoutStatus, $actorType, $actor,
                "Midtrans: {$status['transaction_status']} ({$payment->reference})",
                $pendingReason ? ['pending_reason' => $pendingReason] : []);
        }

        return self::RESULT_APPLIED;
    }

    /**
     * Uang masuk tetapi tidak boleh mengubah transaksi: tandai untuk ditinjau / direfund admin.
     */
    protected function flag(Payment $payment, string $flag, array $status, ?int $gross): string
    {
        $meta = $payment->meta ?? [];
        $meta['needs_refund'] = true;
        $meta['flagged'][] = [
            'flag'               => $flag,
            'transaction_id'     => $status['transaction_id'] ?? null,
            'transaction_status' => $status['transaction_status'] ?? null,
            'gross_amount'       => $gross,
            'at'                 => now()->toIso8601String(),
        ];

        $payment->update([
            // Flag pertama dipertahankan; flag berikutnya tetap tercatat di meta.flagged
            'review_flag' => $payment->review_flag ?? $flag,
            'meta'        => $meta,
        ]);

        Log::warning('midtrans.payment_flagged', [
            'flag'           => $flag,
            'reference'      => $payment->reference,
            'payment_id'     => $payment->id,
            'checkout_id'    => $payment->checkout_id,
            'transaction_id' => $status['transaction_id'] ?? null,
            'gross_amount'   => $gross,
        ]);

        return $flag;
    }

    /**
     * transaction_id & payment_type terbaru. transaction_id bisa berganti untuk order_id yang sama
     * (pelanggan ganti metode di Snap); yang lama disimpan di meta. Pembayaran lunas tidak diubah.
     */
    protected function recordGatewayDetails(Payment $payment, array $status): void
    {
        if ($payment->isPaid()) {
            return;
        }

        $meta = $payment->meta ?? [];
        $transactionId = $status['transaction_id'] ?? null;
        $attributes = [];

        if ($transactionId && $transactionId !== $payment->transaction_id) {
            if ($payment->transaction_id) {
                $meta['previous_transaction_ids'][] = $payment->transaction_id;
            }

            $attributes['transaction_id'] = mb_substr((string) $transactionId, 0, 100);
        }

        if (! empty($status['payment_type'])) {
            $attributes['payment_type'] = mb_substr((string) $status['payment_type'], 0, 50);
            $attributes['method'] = MidtransGateway::methodFor($status['payment_type']) ?? $payment->method;
        }

        $meta['midtrans'] = array_filter([
            'transaction_status' => $status['transaction_status'] ?? null,
            'payment_type'       => $status['payment_type'] ?? null,
            'instruction'        => MidtransGateway::paymentInstruction($status),
            'expiry_time'        => $status['expiry_time'] ?? null,
            'transaction_time'   => $status['transaction_time'] ?? null,
        ], fn ($v) => $v !== null);

        $payment->update($attributes + ['meta' => $meta]);
    }

    protected function lockOwner(Payment $payment): Checkout|Order|null
    {
        if ($payment->checkout_id) {
            return Checkout::whereKey($payment->checkout_id)->lockForUpdate()->first();
        }

        return $payment->order_id ? Order::whereKey($payment->order_id)->lockForUpdate()->first() : null;
    }

    /**
     * Waktu dari Midtrans (format "Y-m-d H:i:s", zona WIB).
     */
    protected function gatewayTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    protected function rupiah(mixed $value): ?int
    {
        try {
            return is_scalar($value) ? Rupiah::parse(is_bool($value) ? '' : $value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function field(?array $payload, string $key, int $max): ?string
    {
        $value = $payload[$key] ?? null;

        return is_scalar($value) ? mb_substr((string) $value, 0, $max) : null;
    }

    /**
     * @return array{http: int, result: string}
     */
    protected function finish(PaymentNotificationLog $record, string $result, int $http): array
    {
        $record->update(['result' => $result, 'processed_at' => now()]);

        return ['http' => $http, 'result' => $result];
    }
}
