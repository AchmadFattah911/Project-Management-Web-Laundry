<?php

namespace App\Services;

use App\Data\BankAccountData;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pembatalan transaksi online setelah bayar oleh pelanggan.
 *
 * Syarat (dicek ulang di server dengan lock): transaksi online lunas, belum pernah dibatalkan,
 * SEMUA item masih 'pending' (cucian belum diterima outlet), dan belum lewat
 * finance.online_cancel_window_minutes sejak paid_at.
 *
 * Checkout tetap 'lunas'; refund_status menjadi diproses, item menjadi 'dibatalkan', dan 1 refund dibuat
 * (UNIQUE checkout_id). Refund API dipanggil SETELAH lock dilepas.
 */
class OnlineCancellationService
{
    public function __construct(
        protected RefundService $refunds,
        protected OrderStateMachine $stateMachine,
        protected AuditLogger $audit,
    ) {}

    public static function windowMinutes(): int
    {
        return (int) config('finance.online_cancel_window_minutes', 15);
    }

    /**
     * @return array{allowed: bool, reason: ?string, deadline: ?Carbon, method: ?string}
     */
    public function eligibility(Checkout $checkout): array
    {
        $deny = fn (string $reason, ?Carbon $deadline = null) => ['allowed' => false, 'reason' => $reason, 'deadline' => $deadline, 'method' => null];

        if ($checkout->isOutlet()) {
            return $deny('Transaksi outlet tidak dibatalkan dari aplikasi.');
        }

        if (! $checkout->isPaid() || ! $checkout->paid_at) {
            return $deny('Transaksi belum lunas.');
        }

        if ($checkout->isCancelledAfterPayment()) {
            return $deny('Transaksi ini sudah dibatalkan.');
        }

        $deadline = $checkout->paid_at->copy()->addMinutes(self::windowMinutes());

        if (now()->greaterThan($deadline)) {
            return $deny('Batas waktu pembatalan (' . self::windowMinutes() . ' menit setelah bayar) sudah lewat.', $deadline);
        }

        $orders = $checkout->relationLoaded('orders') ? $checkout->orders : $checkout->orders()->get();

        if ($orders->isEmpty() || $orders->contains(fn (Order $o) => $o->status !== Order::STATUS_PENDING)) {
            return $deny('Cucian sudah diterima/diproses outlet sehingga tidak bisa dibatalkan.', $deadline);
        }

        $payment = $this->paidPayment($checkout);

        if (! $payment) {
            return $deny('Pembayaran transaksi ini tidak ditemukan.');
        }

        return ['allowed' => true, 'reason' => null, 'deadline' => $deadline, 'method' => RefundService::supportsApi($payment) ? Refund::METHOD_API : Refund::METHOD_MANUAL];
    }

    public function cancel(Checkout $checkout, User $customer, ?BankAccountData $bank, ?string $reason = null): Refund
    {
        $created = false;

        try {
            $refund = DB::transaction(function () use ($checkout, $customer, $bank, $reason, &$created) {
                $locked = Checkout::with('orders')->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->user_id !== (int) $customer->id) {
                    throw new InvalidArgumentException('Transaksi tidak ditemukan.');
                }

                // Klik batal ganda / paralel: request yang menunggu lock menerima refund yang sama
                if ($existing = Refund::where('checkout_id', $locked->id)->first()) {
                    return $existing;
                }

                $eligibility = $this->eligibility($locked);

                if (! $eligibility['allowed']) {
                    throw new InvalidArgumentException($eligibility['reason']);
                }

                if ($eligibility['method'] === Refund::METHOD_MANUAL && ! $bank) {
                    throw new InvalidArgumentException('Metode bayar ini direfund lewat transfer. Isi nama bank, nomor rekening, dan nama pemilik rekening.');
                }

                $payment = Payment::whereKey($this->paidPayment($locked)->id)->lockForUpdate()->firstOrFail();

                $refund = Refund::create([
                    'checkout_id'    => $locked->id,
                    'payment_id'     => $payment->id,
                    'amount'         => $locked->totalRupiah(),
                    'method'         => $eligibility['method'],
                    'status'         => Refund::STATUS_PROCESSING,
                    'refund_key'     => 'RF-' . $locked->code . '-' . Str::lower(Str::random(8)),
                    'reason'         => $reason ? mb_substr($reason, 0, 500) : 'Dibatalkan pelanggan',
                    'bank_name'      => $bank?->bankName,
                    'account_number' => $bank?->accountNumber,
                    'account_holder' => $bank?->accountHolder,
                    'requested_by'   => $customer->id,
                ]);

                $locked->update(['refund_status' => Checkout::REFUND_PROCESSING]);
                $this->stateMachine->cancelOrders($locked, $customer, 'Dibatalkan pelanggan setelah bayar, refund diproses');

                $this->audit->log('transaksi.online.cancel', $locked, ['refund_status' => Checkout::REFUND_NONE], [
                    'refund_status' => Checkout::REFUND_PROCESSING, 'refund_id' => $refund->id, 'method' => $refund->method, 'amount' => $refund->amount,
                ], $reason, $customer);

                $created = true;

                return $refund;
            });
        } catch (UniqueConstraintViolationException) {
            $refund = Refund::where('checkout_id', $checkout->id)->firstOrFail();
        }

        // HTTP ke Midtrans di luar lock; hanya oleh request yang membuat refund
        if ($created && $refund->isApi()) {
            $refund = $this->refunds->processApi($refund);
        }

        return $refund->fresh();
    }

    protected function paidPayment(Checkout $checkout): ?Payment
    {
        return $checkout->payments()->where('status', Payment::STATUS_PAID)->orderBy('id')->first();
    }
}
