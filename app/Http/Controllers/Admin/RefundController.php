<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
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

        return view('admin.refunds.index', compact('payments', 'counts', 'tab'));
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
