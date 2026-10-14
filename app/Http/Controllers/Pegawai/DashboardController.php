<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\Service;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Antrean proses pengerjaan cucian (Non-Self Service)
        $activeProcesses = OrderProcess::with(['order.service', 'order.user', 'pegawai'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        // Pesanan yang butuh diproses hari ini
        $todayOrders = Order::with(['service', 'user'])
            ->whereIn('status', ['pending', 'diproses'])
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'antrean_cuci'    => OrderProcess::where('process', 'cuci')->where('status', 'pending')->count(),
            'antrean_kering'  => OrderProcess::where('process', 'kering')->where('status', 'pending')->count(),
            'antrean_lipat'   => OrderProcess::where('process', 'lipat')->where('status', 'pending')->count(),
            // Cucian yang tuntas dikerjakan hari ini (siap diambil atau sudah diambil)
            'selesai_hari_ini'=> Order::whereIn('status', [Order::STATUS_SIAP_DIAMBIL, Order::STATUS_SELESAI])->whereDate('updated_at', $today)->count(),
        ];

        $services = Service::aktif()->get();

        return view('pegawai.dashboard', compact('activeProcesses', 'todayOrders', 'stats', 'services'));
    }
}
