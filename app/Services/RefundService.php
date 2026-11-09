<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\FinancialEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Gateways\MidtransGateway;
use App\Support\BusinessCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Refund transaksi online yang dibatalkan setelah bayar.
 *
 *   midtrans_api  Refund API Midtrans (metode yang didukung, lihat config finance.midtrans_refundable_types),
 *                 dipanggil TANPA memegang lock DB; refund_key tetap sama saat dicoba ulang. Hasil dikonfirmasi
 *                 respons API, status API (jika duplikat), atau webhook transaction_status=refund.
 *   manual        VA / bank transfer: admin mentransfer ke rekening pelanggan lalu menandai selesai dengan bukti.
 *
 * Entri ledger 'refund' hanya dibuat saat refund SELESAI. Gagal/timeout → status gagal, tanpa ledger, bisa dicoba ulang.
 * Urutan lock: checkout → refund.
 */
class RefundService
{
    public function __construct(
        protected LedgerService $ledger,
        protected ClosedPeriodService $periods,
        protected AuditLogger $audit,
    ) {}

    public function gateway(): MidtransGateway
    {
        return app(MidtransGateway::class);
    }

    public static function supportsApi(Payment $payment): bool
    {
        return $payment->gateway === MidtransGateway::NAME
            && in_array($payment->payment_type, config('finance.midtrans_refundable_types', []), true);
    }

    /**
     * Refund yang perlu tindakan admin (manual diproses / API gagal) dan yang baru selesai.
     */
    /**
     * @param  list<string>  $statuses
     */
    public function paginateForAdmin(array $statuses, int $perPage = 15): LengthAwarePaginator
    {
        return Refund::with(['checkout.user', 'payment', 'requester:id,name', 'processor:id,name'])
            ->whereIn('status', $statuses)
            ->latest('id')
            ->paginate($perPage, pageName: 'refund_page')
            ->withQueryString();
    }

    public function processApi(Refund $refund): Refund
    {
        $refund = $refund->fresh(['payment']);

        if (! $refund->isApi() || $refund->status !== Refund::STATUS_PROCESSING) {
            return $refund;
        }

        Refund::whereKey($refund->id)->increment('attempts');
        $orderId = (string) $refund->payment->reference;

        try {
            $result = $this->gateway()->refund($orderId, $refund->refund_key, $refund->amount, $refund->reason ?: 'Pembatalan pesanan oleh pelanggan');

            if ($result['outcome'] === 'duplicate') {
                // refund_key sudah pernah diterima Midtrans: pastikan lewat API status sebelum dianggap selesai
                $status = $this->gateway()->status($orderId);

                return in_array($status['transaction_status'] ?? null, ['refund', 'partial_refund'], true)
                    ? $this->complete($refund, gatewayResponse: $status)
                    : $this->fail($refund, 'Midtrans menolak refund_key ganda dan status transaksi belum refund.');
            }

            return $result['outcome'] === 'success'
                ? $this->complete($refund, gatewayResponse: $result['body'])
                : $this->fail($refund, 'Midtrans menolak refund: ' . $result['message'], $result['body']);
        } catch (Throwable $e) {
            Log::warning('refund.api_failed', ['refund_id' => $refund->id, 'error' => $e->getMessage()]);

            return $this->fail($refund, $e->getMessage());
        }
    }

    /**
     * Admin mencoba ulang refund API yang gagal (refund_key sama).
     */
    public function retry(Refund $refund, User $admin): Refund
    {
        DB::transaction(function () use ($refund, $admin) {
            $checkout = Checkout::whereKey($refund->checkout_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isApi() || $locked->status !== Refund::STATUS_FAILED) {
                throw new InvalidArgumentException('Hanya refund API yang gagal yang bisa dicoba ulang.');
            }

            $locked->update(['status' => Refund::STATUS_PROCESSING, 'last_error' => null]);
            $checkout->update(['refund_status' => Checkout::REFUND_PROCESSING]);
            $this->audit->log('refund.retry', $locked, ['status' => Refund::STATUS_FAILED], ['status' => Refund::STATUS_PROCESSING], null, $admin);
        });

        return $this->processApi($refund);
    }

    /**
     * Refund manual selesai: admin sudah mentransfer, menyertakan bukti dan catatan.
     */
    public function completeManual(Refund $refund, User $admin, UploadedFile $proof, string $note): Refund
    {
        if ($refund->method !== Refund::METHOD_MANUAL || $refund->status !== Refund::STATUS_PROCESSING) {
            throw new InvalidArgumentException('Refund ini tidak menunggu transfer manual.');
        }

        $this->periods->assertOpen(BusinessCalendar::today(), 'Refund');

        $disk = (string) config('finance.expense.disk', 'local');
        $path = $proof->storeAs('refunds/' . now()->format('Y/m'), Str::uuid() . '.' . strtolower($proof->extension() ?: 'bin'), $disk);

        try {
            return $this->complete($refund, $admin, $path, $note, enforcePeriod: true);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    /**
     * Tandai refund selesai + entri ledger refund (idempoten: selesai dua kali = tetap 1 entri).
     *
     * @param  array<string, mixed>|null  $gatewayResponse
     */
    public function complete(Refund $refund, ?User $actor = null, ?string $proofPath = null, ?string $note = null, ?array $gatewayResponse = null, bool $enforcePeriod = false): Refund
    {
        return DB::transaction(function () use ($refund, $actor, $proofPath, $note, $gatewayResponse, $enforcePeriod) {
            $checkout = Checkout::whereKey($refund->checkout_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::with('payment')->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Refund::STATUS_DONE) {
                return $locked;
            }

            $today = BusinessCalendar::today();

            if ($enforcePeriod) {
                $this->periods->assertOpen($today, 'Refund');
            }

            $locked->update([
                'status'           => Refund::STATUS_DONE,
                'completed_at'     => now(),
                'processed_by'     => $actor?->id,
                'proof_path'       => $proofPath ?? $locked->proof_path,
                'note'             => $note ?? $locked->note,
                'last_error'       => null,
                'gateway_response' => $gatewayResponse ?? $locked->gateway_response,
            ]);
            $checkout->update(['refund_status' => Checkout::REFUND_DONE]);

            $this->ledger->append([
                'entry_type'     => FinancialEntry::TYPE_REFUND,
                'channel'        => $checkout->channel,
                'source_type'    => FinancialEntry::SOURCE_REFUND,
                'source_id'      => $locked->id,
                'amount'         => -$locked->amount,
                'payment_method' => LedgerService::paymentMethodOf($locked->payment),
                'occurred_at'    => now(),
                // Konfirmasi gateway tidak bisa ditolak: jika hari ini sudah tutup buku, tetap dicatat hari ini
                'business_date'  => $enforcePeriod ? $today : $this->ledger->openDateFor(now()),
                'checkout_id'    => $checkout->id,
                'created_by'     => $actor?->id,
            ]);

            $this->audit->log('refund.complete', $locked, ['status' => $refund->status], [
                'status' => Refund::STATUS_DONE, 'amount' => $locked->amount, 'method' => $locked->method,
            ], $note, $actor);

            return $locked;
        });
    }

    /**
     * Konfirmasi dari webhook/status Midtrans (transaction_status refund) untuk pembayaran yang sedang direfund.
     * Dipanggil di dalam transaksi DB pemroses notifikasi.
     */
    public function confirmFromGateway(Payment $payment, array $status): bool
    {
        $refund = Refund::where('payment_id', $payment->id)
            ->where('method', Refund::METHOD_API)
            ->whereIn('status', [Refund::STATUS_PROCESSING, Refund::STATUS_FAILED])
            ->first();

        if (! $refund) {
            return false;
        }

        $this->complete($refund, gatewayResponse: $status);

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $gatewayResponse
     */
    protected function fail(Refund $refund, string $error, ?array $gatewayResponse = null): Refund
    {
        return DB::transaction(function () use ($refund, $error, $gatewayResponse) {
            $checkout = Checkout::whereKey($refund->checkout_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            // Webhook bisa saja sudah menyelesaikan refund lebih dulu
            if ($locked->status === Refund::STATUS_DONE) {
                return $locked;
            }

            $locked->update([
                'status'           => Refund::STATUS_FAILED,
                'last_error'       => mb_substr($error, 0, 500),
                'gateway_response' => $gatewayResponse ?? $locked->gateway_response,
            ]);
            $checkout->update(['refund_status' => Checkout::REFUND_FAILED]);

            $this->audit->log('refund.failed', $locked, ['status' => Refund::STATUS_PROCESSING], ['status' => Refund::STATUS_FAILED], $error);

            return $locked;
        });
    }
}
