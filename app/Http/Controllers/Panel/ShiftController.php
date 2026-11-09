<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Http\Requests\Panel\CloseShiftRequest;
use App\Http\Requests\Panel\OpenShiftRequest;
use App\Models\CashShift;
use App\Services\ShiftService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Kas harian per shift: buka (saldo awal), tutup (hitung uang fisik), verifikasi admin.
 * Pegawai hanya melihat shift miliknya (CashShiftPolicy).
 */
class ShiftController extends Controller
{
    use AuthorizesRequests, HandlesServiceErrors;

    public function __construct(protected ShiftService $shifts) {}

    public function index(Request $request)
    {
        $active = $this->shifts->activeFor($request->user());

        return view('panel.shift.index', [
            'active'        => $active,
            'activeSummary' => $active ? $this->shifts->summary($active) : null,
            'shifts'        => $this->shifts->paginate($request->user(), $request->query('status')),
            'status'        => $request->query('status'),
        ]);
    }

    public function open(OpenShiftRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $this->shifts->open($request->user(), (int) $request->validated('opening_balance'), $request->validated('submission_token'));

            return redirect()->route('panel.shift.index')->with('success', 'Shift dibuka. Transaksi tunai sekarang tercatat ke shift ini.');
        });
    }

    public function show(CashShift $shift)
    {
        $this->authorize('view', $shift);

        return view('panel.shift.show', [
            'shift'   => $shift->load(['user', 'verifier']),
            'summary' => $this->shifts->summary($shift),
            'entries' => $this->shifts->entries($shift),
        ]);
    }

    public function close(CloseShiftRequest $request, CashShift $shift)
    {
        $this->authorize('view', $shift);

        return $this->attempt(function () use ($request, $shift) {
            $closed = $this->shifts->close($shift, $request->user(), $request->denominations(), $request->countedTotal(), $request->validated('note'), $request->validated('submission_token'));
            $difference = $closed->difference;

            return redirect()->route('panel.shift.show', $closed)->with($difference === 0 ? 'success' : 'status', $difference === 0
                ? 'Shift ditutup. Uang di laci sesuai.'
                : 'Shift ditutup dengan selisih Rp ' . number_format($difference, 0, ',', '.') . '. Admin akan memverifikasi.');
        });
    }

    public function verify(Request $request, CashShift $shift)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return $this->attempt(function () use ($request, $shift, $note) {
            $this->shifts->verify($shift, $request->user(), $note);

            return back()->with('success', "Shift #{$shift->id} terverifikasi.");
        });
    }
}
