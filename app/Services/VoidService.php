<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Checkout;
use App\Models\FinancialEntry;
use App\Models\User;
use App\Models\VoidRequest;
use App\Support\BusinessCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Void transaksi outlet: pegawai mengajukan dengan alasan, admin menyetujui/menolak.
 * Transaksi asli tidak diubah/dihapus. Void yang disetujui atas transaksi lunas membuat entri
 * void_reversal di ledger pada tanggal bisnis persetujuan (ditolak jika tanggal itu sudah tutup buku).
 * Urutan lock: checkout → void_request.
 */
class VoidService
{
    public const MIN_REASON = 10;

    public function __construct(
        protected LedgerService $ledger,
        protected ClosedPeriodService $periods,
        protected AuditLogger $audit,
    ) {}

    public function paginate(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return VoidRequest::with(['checkout.user', 'requester', 'decider'])
            ->where('status', $status)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function request(Checkout $checkout, string $reason, User $actor, string $token): VoidRequest
    {
        $this->assertReason($reason);

        if ($existing = VoidRequest::where('submission_token', $token)->first()) {
            return $existing;
        }

        if (! $checkout->isOutlet()) {
            throw new InvalidArgumentException('Void hanya untuk transaksi outlet. Transaksi aplikasi dibatalkan lewat pembatalan & refund.');
        }

        try {
            return DB::transaction(function () use ($checkout, $reason, $actor, $token) {
                $locked = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();

                if ($locked->isVoided()) {
                    throw new InvalidArgumentException("Transaksi {$locked->code} sudah di-void.");
                }

                if (! in_array($locked->status, [Checkout::STATUS_AWAITING, Checkout::STATUS_PAID], true)) {
                    throw new InvalidArgumentException("Transaksi {$locked->code} tidak bisa di-void.");
                }

                $request = VoidRequest::create([
                    'checkout_id'      => $locked->id,
                    'requested_by'     => $actor->id,
                    'reason'           => $reason,
                    'status'           => VoidRequest::STATUS_PENDING,
                    'submission_token' => $token,
                ]);

                $this->audit->log('transaksi.void.request', $locked, null, ['void_request_id' => $request->id], $reason, $actor);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            return VoidRequest::where('submission_token', $token)->first()
                ?? throw new InvalidArgumentException('Transaksi ini sudah punya pengajuan void yang menunggu persetujuan.');
        }
    }

    public function approve(VoidRequest $request, User $admin, ?string $note = null): VoidRequest
    {
        return DB::transaction(function () use ($request, $admin, $note) {
            $checkout = Checkout::whereKey($request->checkout_id)->lockForUpdate()->firstOrFail();
            $locked = $this->lockPending($request);

            $today = BusinessCalendar::today();
            $this->periods->assertOpen($today, 'Void');

            $sale = FinancialEntry::where('checkout_id', $checkout->id)->where('entry_type', FinancialEntry::TYPE_SALE)->first();
            $amount = (int) ($sale?->amount ?? 0);

            $locked->update([
                'status'        => VoidRequest::STATUS_APPROVED,
                'amount'        => $amount,
                'decided_by'    => $admin->id,
                'decided_at'    => now(),
                'decision_note' => $note,
            ]);

            if ($amount > 0) {
                // Kas tunai dikembalikan dari laci shift asal selama shift itu masih buka
                $shiftOpen = $sale->shift_id && CashShift::whereKey($sale->shift_id)->where('status', CashShift::STATUS_OPEN)->exists();

                $this->ledger->append([
                    'entry_type'     => FinancialEntry::TYPE_VOID_REVERSAL,
                    'channel'        => FinancialEntry::CHANNEL_OUTLET,
                    'source_type'    => FinancialEntry::SOURCE_VOID,
                    'source_id'      => $locked->id,
                    'amount'         => -$amount,
                    'payment_method' => $sale->payment_method,
                    'occurred_at'    => now(),
                    'business_date'  => $today,
                    'shift_id'       => $shiftOpen ? $sale->shift_id : null,
                    'checkout_id'    => $checkout->id,
                    'created_by'     => $admin->id,
                ]);
            }

            $this->audit->log('transaksi.void.approve', $checkout, ['void_status' => VoidRequest::STATUS_PENDING], [
                'void_status' => VoidRequest::STATUS_APPROVED, 'void_request_id' => $locked->id, 'reversed' => $amount,
            ], $note ?? $locked->reason, $admin);

            return $locked;
        });
    }

    public function reject(VoidRequest $request, User $admin, string $note): VoidRequest
    {
        $this->assertReason($note);

        return DB::transaction(function () use ($request, $admin, $note) {
            Checkout::whereKey($request->checkout_id)->lockForUpdate()->firstOrFail();
            $locked = $this->lockPending($request);

            $locked->update([
                'status'        => VoidRequest::STATUS_REJECTED,
                'decided_by'    => $admin->id,
                'decided_at'    => now(),
                'decision_note' => $note,
            ]);

            $this->audit->log('transaksi.void.reject', $locked->checkout, ['void_status' => VoidRequest::STATUS_PENDING], [
                'void_status' => VoidRequest::STATUS_REJECTED, 'void_request_id' => $locked->id,
            ], $note, $admin);

            return $locked;
        });
    }

    protected function lockPending(VoidRequest $request): VoidRequest
    {
        $locked = VoidRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== VoidRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Pengajuan void ini sudah diputuskan.');
        }

        return $locked;
    }

    protected function assertReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < self::MIN_REASON) {
            throw new InvalidArgumentException('Alasan wajib diisi minimal ' . self::MIN_REASON . ' karakter.');
        }
    }
}
