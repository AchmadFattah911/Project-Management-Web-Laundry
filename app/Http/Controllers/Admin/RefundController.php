<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\IdempotencyException;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Halaman "Perlu Refund": uang pelanggan yang masuk tetapi tidak dipakai untuk melunasi transaksi
 * (nominal tidak sesuai, dibayar dua kali, atau dibayar setelah transaksi ditutup). Refund dilakukan
 * admin di luar sistem (dashboard Midtrans / transfer manual), lalu ditandai di sini dengan catatan wajib.
 */
class RefundController extends Controller
{
    public const TAB_OPEN = 'perlu';
    public const TAB_DONE = 'selesai';

    public function index(Request $request)
    {
        $tab = $request->input('tab') === self::TAB_DONE ? self::TAB_DONE : self::TAB_OPEN;

        $payments = Payment::with(['checkout.user', 'order.user', 'refundedBy'])
            ->flagged()
            ->when($tab === self::TAB_OPEN, fn ($q) => $q->whereNull('refunded_at'), fn ($q) => $q->whereNotNull('refunded_at'))
            ->latest($tab === self::TAB_OPEN ? 'updated_at' : 'refunded_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            self::TAB_OPEN => Payment::flagged()->whereNull('refunded_at')->count(),
            self::TAB_DONE => Payment::flagged()->whereNotNull('refunded_at')->count(),
        ];

        // Pembatalan setelah bayar: refund manual menunggu transfer / refund API gagal, atau yang sudah selesai
        $cancellations = app(RefundService::class)->paginateForAdmin(
            $tab === self::TAB_OPEN ? [Refund::STATUS_PROCESSING, Refund::STATUS_FAILED] : [Refund::STATUS_DONE]
        );
        $counts[self::TAB_OPEN] += Refund::whereIn('status', [Refund::STATUS_PROCESSING, Refund::STATUS_FAILED])->count();
        $counts[self::TAB_DONE] += Refund::where('status', Refund::STATUS_DONE)->count();

        return view('admin.refunds.index', compact('payments', 'counts', 'tab', 'cancellations'));
    }

    /**
     * Refund manual selesai: admin sudah transfer ke rekening pelanggan; bukti & catatan wajib.
     */
    public function completeCancellation(Request $request, Refund $refund, RefundService $refunds)
    {
        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . (int) config('finance.expense.receipt_max_kb')],
            'note'  => ['required', 'string', 'min:5', 'max:1000'],
        ], [], ['proof' => 'bukti transfer', 'note' => 'catatan']);

        try {
            $refunds->completeManual($refund, $request->user(), $validated['proof'], trim($validated['note']));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Refund {$refund->checkout->code} ditandai selesai dan dicatat di ledger.");
    }

    public function retryCancellation(Request $request, Refund $refund, RefundService $refunds)
    {
        try {
            $result = $refunds->retry($refund, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $result->status === Refund::STATUS_DONE
            ? back()->with('success', "Refund {$refund->checkout->code} berhasil diproses Midtrans.")
            : back()->with('error', "Refund {$refund->checkout->code} masih gagal: {$result->last_error}");
    }

    public function cancellationProof(Refund $refund)
    {
        $disk = Storage::disk((string) config('finance.expense.disk', 'local'));
        abort_unless($refund->proof_path && $disk->exists($refund->proof_path), 404);

        return $disk->response($refund->proof_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function markRefunded(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'refund_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'refund_note.required' => 'Catatan refund wajib diisi (mis. nomor bukti transfer / ID refund Midtrans).',
            'refund_note.min'      => 'Catatan refund minimal 5 karakter.',
        ]);

        $outcome = DB::transaction(function () use ($payment, $validated, $request) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->review_flag, Payment::REVIEW_FLAGS, true)) {
                return 'not_flagged';
            }

            // Klik ganda / tab lain: catatan pertama tidak ditimpa
            if ($locked->refunded_at) {
                return 'already';
            }

            $locked->update([
                'refunded_at' => now(),
                'refunded_by' => $request->user()->id,
                'refund_note' => trim($validated['refund_note']),
            ]);

            Log::info('payment.refund_marked', [
                'payment_id'  => $locked->id,
                'reference'   => $locked->reference,
                'review_flag' => $locked->review_flag,
                'admin_id'    => $request->user()->id,
                'note'        => $locked->refund_note,
            ]);

            return 'marked';
        });

        return match ($outcome) {
            'not_flagged' => back()->with('error', 'Pembayaran ini tidak termasuk daftar Perlu Refund.'),
            'already'     => back()->with('status', "Pembayaran {$payment->reference} sudah ditandai direfund sebelumnya."),
            default       => back()->with('success', "Pembayaran {$payment->reference} ditandai sudah direfund."),
        };
    }
}
