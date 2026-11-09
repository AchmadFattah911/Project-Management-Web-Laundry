<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Payment;
use App\Payments\Gateways\MidtransGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jaring pengaman jika webhook Midtrans hilang / terlambat:
 * - reconcilePending: tagihan Midtrans yang masih pending dicek ke API status (jalur sama dengan webhook);
 * - expireOverdue: transaksi lewat batas bayar + masa tenggang ditutup kadaluarsa, SETELAH dipastikan
 *   ke Midtrans bahwa belum dibayar (dan tagihannya ditutup di Midtrans).
 *
 * Urutan lock: checkout → payment (sama dengan webhook dan /bayar).
 */
class PaymentReconciliationService
{
    public const EXPIRE_EXPIRED = 'expired';
    public const EXPIRE_RESOLVED = 'resolved_by_gateway';
    public const EXPIRE_SKIPPED = 'skipped';

    public function __construct(
        protected MidtransNotificationService $midtrans,
        protected OrderStateMachine $stateMachine,
    ) {}

    public static function serverKeyConfigured(): bool
    {
        return (string) config('payment.gateways.midtrans.server_key') !== '';
    }

    /**
     * @return array<string, int>  jumlah per hasil (applied, no_change, duplicate, error, ...)
     */
    public function reconcilePending(int $limit = 100): array
    {
        $stats = [];

        $payments = Payment::where('gateway', MidtransGateway::NAME)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('snap_token')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($payments as $payment) {
            try {
                $result = $this->midtrans->sync($payment, OrderStateMachine::ACTOR_RECONCILE)['result'];
            } catch (Throwable $e) {
                Log::warning('payments.reconcile_failed', ['reference' => $payment->reference, 'error' => $e->getMessage()]);
                $result = 'error';
            }

            $stats[$result] = ($stats[$result] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Transaksi menunggu pembayaran yang batas bayarnya (payment_deadline, atau created_at + masa bayar untuk
     * transaksi lama) sudah lewat lebih dari $graceMinutes.
     *
     * @return array<string, int>
     */
    public function expireOverdue(int $graceMinutes = 5, int $limit = 100): array
    {
        $cutoff = now()->subMinutes($graceMinutes);
        $legacyCutoff = $cutoff->copy()->subMinutes((int) config('payment.expiry_minutes', 60));

        // Hanya transaksi online: transaksi outlet "bayar saat ambil" tidak punya batas bayar
        $checkouts = Checkout::where('status', Checkout::STATUS_AWAITING)
            ->where('channel', Checkout::CHANNEL_ONLINE)
            ->where(fn ($q) => $q->where('payment_deadline', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('payment_deadline')->where('created_at', '<', $legacyCutoff)))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [];

        foreach ($checkouts as $checkout) {
            $result = $this->expireCheckout($checkout);
            $stats[$result] = ($stats[$result] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Menutup satu transaksi yang lewat batas bayar. Rekonsiliasi dulu: jika Midtrans menyatakan sudah dibayar,
     * transaksi menjadi lunas (bukan kadaluarsa). Jika status tidak bisa dipastikan, dilewati (dicoba lagi nanti).
     */
    public function expireCheckout(Checkout $checkout): string
    {
        $active = $checkout->payments()
            ->where('gateway', MidtransGateway::NAME)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('snap_token')
            ->get();

        foreach ($active as $payment) {
            try {
                $synced = $this->midtrans->sync($payment, OrderStateMachine::ACTOR_RECONCILE);
            } catch (Throwable $e) {
                Log::warning('checkouts.expire_status_failed', ['checkout_code' => $checkout->code, 'reference' => $payment->reference, 'error' => $e->getMessage()]);

                return self::EXPIRE_SKIPPED;
            }

            if (! $checkout->fresh()->isAwaitingPayment()) {
                return self::EXPIRE_RESOLVED;
            }

            // Masih pending di Midtrans (VA belum dibayar): tutup dulu di Midtrans agar tidak bisa dibayar lagi
            if (($synced['status']['transaction_status'] ?? null) === 'pending') {
                try {
                    $this->midtrans->gateway()->expire($payment->reference);
                } catch (Throwable $e) {
                    Log::warning('checkouts.expire_gateway_failed', ['checkout_code' => $checkout->code, 'reference' => $payment->reference, 'error' => $e->getMessage()]);

                    // Ditolak karena baru saja dibayar? Terapkan statusnya; selain itu coba lagi di putaran berikutnya
                    try {
                        $this->midtrans->sync($payment, OrderStateMachine::ACTOR_RECONCILE);
                    } catch (Throwable) {
                        // diabaikan: dicoba lagi di putaran berikutnya
                    }

                    return $checkout->fresh()->isAwaitingPayment() ? self::EXPIRE_SKIPPED : self::EXPIRE_RESOLVED;
                }
            }
        }

        $expired = DB::transaction(function () use ($checkout) {
            $locked = Checkout::whereKey($checkout->id)->lockForUpdate()->first();

            if (! $locked?->isAwaitingPayment()) {
                return false;
            }

            $locked->payments()->where('status', Payment::STATUS_PENDING)->lockForUpdate()->get()
                ->each(fn (Payment $p) => $p->update([
                    'status' => Payment::STATUS_EXPIRED,
                    'meta'   => array_merge($p->meta ?? [], ['closed_reason' => 'checkout_expired', 'closed_at' => now()->toIso8601String()]),
                ]));

            return $this->stateMachine->transitionCheckout($locked, Checkout::STATUS_EXPIRED, OrderStateMachine::ACTOR_SCHEDULER, null,
                'Batas waktu pembayaran habis', ['pending_reason' => 'kedaluwarsa']);
        });

        if ($expired) {
            Log::info('checkout.expired', ['checkout_code' => $checkout->code]);
        }

        return $expired ? self::EXPIRE_EXPIRED : self::EXPIRE_RESOLVED;
    }
}
