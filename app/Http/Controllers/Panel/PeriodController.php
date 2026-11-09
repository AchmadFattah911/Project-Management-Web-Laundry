<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Services\ClosedPeriodService;
use Illuminate\Http\Request;

/**
 * Tutup buku (can:periode.close). Periode terkunci tidak bisa dibuka lagi dari aplikasi.
 */
class PeriodController extends Controller
{
    use HandlesServiceErrors;

    public function __construct(protected ClosedPeriodService $periods) {}

    public function index()
    {
        return view('panel.periode.index', ['periods' => $this->periods->paginate()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end'   => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'note'         => ['nullable', 'string', 'max:500'],
        ], [], ['period_start' => 'tanggal awal', 'period_end' => 'tanggal akhir']);

        return $this->attempt(function () use ($validated, $request) {
            $this->periods->close($validated['period_start'], $validated['period_end'], $request->user(), $validated['note'] ?? null);

            return back()->with('success', "Periode {$validated['period_start']} s.d. {$validated['period_end']} ditutup buku.");
        });
    }
}
