<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;

/**
 * URL laporan keuangan pegawai yang lama. Laporan keuangan kini hanya untuk admin (panel.laporan.*,
 * dibaca dari ledger); pegawai hanya melihat ringkasan shift miliknya sendiri.
 */
class FinanceController extends Controller
{
    public function index()
    {
        return redirect()->route('panel.shift.index')
            ->with('status', 'Laporan keuangan hanya untuk admin. Berikut ringkasan kas shift Anda.');
    }

    public function export()
    {
        abort(403, 'Export laporan keuangan hanya untuk admin.');
    }
}
