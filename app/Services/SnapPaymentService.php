<?php

namespace App\Services;

use App\Exceptions\IdempotencyException;
use App\Models\Checkout;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\MidtransGateway;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Pembayaran transaksi keranjang lewat Midtrans Snap (aktif jika PAYMENT_ENABLED=true).
 *
 * Satu tagihan aktif per transaksi:
 * - Ada payment pending ber-snap_token → token yang SAMA dikembalikan (klik ganda, reload, tab ganda).
 * - Payment pending tanpa snap_token = sedang dibuat request lain → 409 "sedang disiapkan";
 *   jika lebih lama dari in_flight_seconds (proses mati di tengah jalan) → ditutup sebagai failed.
 * - Pembuatan Snap gagal / timeout → payment ditandai failed; percobaan berikutnya memakai {kode}-P{n+1}.
 *   Jika ternyata Midtrans sempat membuat tagihan itu dan pelanggan membayarnya, webhook untuk reference yang
 *   sudah ditutup ditandai paid_after_closed → tidak ada uang yang hilang tanpa jejak.
 * - UNIQUE payments.pending_owner menjadi pengaman terakhir di database.
 *
 * Urutan lock selalu: checkout → payment (sama dengan webhook, rekonsiliasi, job kedaluwarsa).
 * Panggilan HTTP ke Midtrans tidak pernah dilakukan sambil memegang lock.
 */
class SnapPaymentService
{
    public const ALREADY_PAID_MESSAGE = 'Pembayaran Anda sudah masuk, sedang diverifikasi. Transaksi tidak bisa dibatalkan.';

    public function __construct(
        protected OrderStateMachine $stateMachine,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('payment.enabled');
    }

    public function gateway(): MidtransGateway
    {
        return app(MidtransGateway::class);
    }

    /**
     * @return array{payment: Payment, replayed: bool}
     *
     * @throws IdempotencyException  tagihan sedang dibuat request lain (409)
     * @throws InvalidArgumentException  transaksi tidak bisa dibayar (422)
     * @throws RuntimeException  Midtrans gagal membuat tagihan
     */
    public function pay(Checkout $checkout, User $user): array
    {
        $prepared = DB::transaction(function () use ($checkout, $user) {
            $locked = $this->lockPayableCheckout($checkout, $user);

            $pending = $locked->payments()->where('status', Payment::STATUS_PENDING)->lockForUpdate()->latest('id')->first();

            if ($pending) {
                if ($pending->gateway === MidtransGateway::NAME && $pending->snap_token) {
                    return ['payment' => $pending, 'replayed' => true];
                }

                if ($pending->gateway === MidtransGateway::NAME && $this->isInFlight($pending)) {
                    throw new IdempotencyException(IdempotencyException::IN_PROGRESS, 'Pembayaran sedang disiapkan. Tunggu beberapa detik lalu coba lagi.');
                }

                // Tagihan macet (proses mati sebelum Snap merespons) atau dari gateway lain (sebelum flag dinyalakan)
                $this->closePayment($pending, Payment::STATUS_FAILED, $pending->gateway === MidtransGateway::NAME ? 'stale_in_flight' : 'replaced_by_midtrans');
            }

            $attempt = $this->nextAttempt($locked);

            $payment = $locked->payments()->create([
                'amount'     => Rupiah::toDecimal($locked->totalRupiah()),
                // Metode asli baru diketahui setelah pelanggan memilih di Snap (diisi dari payment_type oleh webhook)
                'method'     => 'transfer',
                'gateway'    => MidtransGateway::NAME,
                'reference'  => $attempt['reference'],
                'status'     => Payment::STATUS_PENDING,
                'expires_at' => $locked->paymentDeadline(),
                'meta'       => ['attempt' => $attempt['n']],
            ]);

            return ['payment' => $payment, 'replayed' => false];
        });

        if ($prepared['replayed']) {
            Log::info('snap.reused', $this->context($checkout, $prepared['payment']));

            return $prepared;
        }

        return ['payment' => $this->createSnap($checkout, $prepared['payment']), 'replayed' => false];
    }

    /**
     * Pelanggan membatalkan transaksi yang belum dibayar. Tagihan Midtrans yang masih aktif ditutup lebih dulu;
     * jika Midtrans menyatakan sudah dibayar atau tidak bisa dihubungi, pembatalan ditolak (uang tidak boleh
     * masuk ke transaksi yang sudah dibatalkan).
     */
    public function cancel(Checkout $checkout, User $user): void
    {
        $this->lockPayableCheckout($checkout, $user, forCancel: true, lock: false);

        $pendings = $checkout->payments()->where('status', Payment::STATUS_PENDING)->get();

        foreach ($pendings->where('gateway', MidtransGateway::NAME) as $payment) {
            if (! $payment->snap_token && $this->isInFlight($payment)) {
                throw new InvalidArgumentException('Pembayaran sedang disiapkan. Tunggu beberapa detik lalu coba batalkan lagi.');
            }

            if ($payment->snap_token) {
                $this->closeAtMidtrans($payment);
            }
        }

        DB::transaction(function () use ($checkout, $user) {
            $locked = $this->lockPayableCheckout($checkout, $user, forCancel: true);

            $locked->payments()->where('status', Payment::STATUS_PENDING)->lockForUpdate()->get()
                ->each(fn (Payment $p) => $this->closePayment($p, Payment::STATUS_EXPIRED, 'cancelled_by_customer'));

            $this->stateMachine->transitionCheckout($locked, Checkout::STATUS_CANCELLED, OrderStateMachine::ACTOR_PELANGGAN, $user, 'Dibatalkan pelanggan');
        });

        Log::info('checkout.cancelled', ['checkout_code' => $checkout->code, 'user_id' => $user->id]);
    }

    /**
     * Popup Snap ditutup tanpa menyelesaikan pembayaran. Hanya informasi untuk pelanggan, tidak mengubah status.
     * Tidak menimpa 'menunggu_transfer' (VA sudah terbit, pelanggan tinggal transfer).
     */
    public function markPopupClosed(Checkout $checkout): void
    {
        Checkout::whereKey($checkout->id)
            ->where('status', Checkout::STATUS_AWAITING)
            ->where(fn ($q) => $q->whereNull('pending_reason')->orWhere('pending_reason', 'popup_ditutup'))
            ->update(['pending_reason' => 'popup_ditutup']);
    }

    // ─────────────────────────────────────────────────────────────

    protected function createSnap(Checkout $checkout, Payment $payment): Payment
    {
        try {
            $charge = $this->gateway()->createCharge($payment, $payment->reference);
        } catch (Throwable $e) {
            // Gagal / timeout: tutup percobaan ini agar tidak mengunci transaksi; berikutnya -P{n+1}
            DB::transaction(function () use ($checkout, $payment, $e) {
                Checkout::whereKey($checkout->id)->lockForUpdate()->first();
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

                if ($locked?->status === Payment::STATUS_PENDING) {
                    $this->closePayment($locked, Payment::STATUS_FAILED, 'snap_create_failed', ['error' => mb_substr($e->getMessage(), 0, 500)]);
                }
            });

            Log::error('snap.create_failed', $this->context($checkout, $payment) + ['error' => $e->getMessage()]);

            throw new RuntimeException('Pembayaran gagal disiapkan. Silakan coba lagi beberapa saat lagi.', 0, $e);
        }

        return DB::transaction(function () use ($checkout, $payment, $charge) {
            Checkout::whereKey($checkout->id)->lockForUpdate()->first();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Ditutup request lain selama menunggu Midtrans (dianggap macet): jangan hidupkan lagi
            if ($locked->status !== Payment::STATUS_PENDING) {
                Log::warning('snap.token_for_closed_payment', $this->context($checkout, $locked));

                throw new RuntimeException('Pembayaran gagal disiapkan. Silakan coba lagi.');
            }

            $locked->update([
                'snap_token'   => $charge->meta['snap_token'],
                'checkout_url' => $charge->checkoutUrl,
            ]);

            Log::info('snap.created', $this->context($checkout, $locked));

            return $locked;
        });
    }

    /**
     * Mengunci transaksi & memastikan masih bisa dibayar/dibatalkan. Pemilik sudah dicek policy di controller;
     * dicek ulang di sini agar service aman dipanggil dari mana pun.
     */
    protected function lockPayableCheckout(Checkout $checkout, User $user, bool $forCancel = false, bool $lock = true): Checkout
    {
        $query = Checkout::whereKey($checkout->id);
        $locked = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();

        if ((int) $locked->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Transaksi tidak ditemukan.');
        }

        if (! $locked->isAwaitingPayment()) {
            throw new InvalidArgumentException($locked->isPaid()
                ? "Transaksi {$locked->code} sudah lunas."
                : "Transaksi {$locked->code} sudah {$locked->status} dan tidak bisa " . ($forCancel ? 'dibatalkan' : 'dibayar') . ' lagi.');
        }

        if (! $forCancel && $locked->paymentDeadline()->isPast()) {
            throw new InvalidArgumentException('Batas waktu pembayaran sudah lewat. Silakan buat pesanan baru.');
        }

        return $locked;
    }

    /**
     * Sebelum dibatalkan: pastikan belum dibayar di Midtrans, lalu tutup tagihannya.
     */
    protected function closeAtMidtrans(Payment $payment): void
    {
        try {
            $status = $this->gateway()->status($payment->reference);
        } catch (Throwable $e) {
            Log::warning('snap.cancel_status_failed', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            throw new InvalidArgumentException('Status pembayaran belum bisa dicek. Coba batalkan lagi beberapa saat lagi.');
        }

        if ($this->isPaidStatus($status)) {
            throw new InvalidArgumentException(self::ALREADY_PAID_MESSAGE);
        }

        if (($status['transaction_status'] ?? null) === 'pending') {
            try {
                $this->gateway()->expire($payment->reference);
            } catch (Throwable $e) {
                Log::warning('snap.cancel_expire_failed', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

                // Race: pelanggan membayar di antara cek status dan expire → Midtrans menolak expire.
                // Pembatalan dibatalkan, status tidak diubah; pelunasan menyusul lewat webhook/rekonsiliasi.
                if ($this->paidAtMidtrans($payment)) {
                    throw new InvalidArgumentException(self::ALREADY_PAID_MESSAGE);
                }

                throw new InvalidArgumentException('Tagihan belum bisa ditutup di Midtrans. Coba batalkan lagi beberapa saat lagi.');
            }
        }
    }

    protected function paidAtMidtrans(Payment $payment): bool
    {
        try {
            return $this->isPaidStatus($this->gateway()->status($payment->reference));
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function isPaidStatus(?array $status): bool
    {
        return in_array($status['transaction_status'] ?? null, ['settlement', 'capture'], true);
    }

    protected function isInFlight(Payment $payment): bool
    {
        return $payment->updated_at->greaterThan(now()->subSeconds((int) config('payment.gateways.midtrans.in_flight_seconds', 45)));
    }

    /**
     * @return array{n: int, reference: string}
     */
    protected function nextAttempt(Checkout $checkout): array
    {
        $n = $checkout->payments()->where('gateway', MidtransGateway::NAME)->count() + 1;

        while (Payment::where('reference', "{$checkout->code}-P{$n}")->exists()) {
            $n++;
        }

        return ['n' => $n, 'reference' => "{$checkout->code}-P{$n}"];
    }

    protected function closePayment(Payment $payment, string $status, string $reason, array $extra = []): void
    {
        $payment->update([
            'status' => $status,
            'meta'   => array_merge($payment->meta ?? [], ['closed_reason' => $reason, 'closed_at' => now()->toIso8601String()], $extra),
        ]);
    }

    protected function context(Checkout $checkout, Payment $payment): array
    {
        return ['checkout_code' => $checkout->code, 'reference' => $payment->reference, 'payment_id' => $payment->id];
    }
}
