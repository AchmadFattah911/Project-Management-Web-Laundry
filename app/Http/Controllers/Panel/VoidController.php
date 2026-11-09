<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Http\Requests\Panel\ReasonRequest;
use App\Http\Requests\Panel\StoreVoidRequest;
use App\Models\Checkout;
use App\Models\VoidRequest;
use App\Services\VoidService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Void transaksi outlet: pegawai mengajukan (can:transaksi.void.request), admin memutuskan (can:transaksi.void.approve).
 */
class VoidController extends Controller
{
    use AuthorizesRequests, HandlesServiceErrors;

    public function __construct(protected VoidService $voids) {}

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), VoidRequest::STATUS_LABELS) ? $request->query('status') : VoidRequest::STATUS_PENDING;

        return view('panel.void.index', [
            'status'   => $status,
            'requests' => $this->voids->paginate($status),
        ]);
    }

    public function store(StoreVoidRequest $request, Checkout $checkout)
    {
        $this->authorize('viewInPanel', $checkout);

        return $this->attempt(function () use ($request, $checkout) {
            $this->voids->request($checkout, $request->reason(), $request->user(), $request->validated('submission_token'));

            return back()->with('success', "Pengajuan void {$checkout->code} dikirim ke admin.");
        }, 'ajukan-void');
    }

    public function approve(Request $request, VoidRequest $voidRequest)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        return $this->attempt(function () use ($request, $voidRequest, $note) {
            $this->voids->approve($voidRequest, $request->user(), $note);

            return back()->with('success', "Void {$voidRequest->checkout->code} disetujui. Entri pembalik dicatat di ledger.");
        });
    }

    public function reject(ReasonRequest $request, VoidRequest $voidRequest)
    {
        return $this->attempt(function () use ($request, $voidRequest) {
            $this->voids->reject($voidRequest, $request->user(), $request->reason());

            return back()->with('success', "Pengajuan void {$voidRequest->checkout->code} ditolak.");
        });
    }
}
