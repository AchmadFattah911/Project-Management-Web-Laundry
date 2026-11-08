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

        // Hanya pesanan yang sudah dibayar yang masuk antrean pengerjaan
        $paid = fn ($order) => $order->paymentStatus('paid');
        $queue = fn (string $process) => OrderProcess::whereHas('order', $paid)->where('process', $process)->where('status', 'pending')->count();

        // Antrean proses pengerjaan cucian (Non-Self Service)
        $activeProcesses = OrderProcess::with(['order.service', 'order.user', 'pegawai'])
            ->whereHas('order', $paid)
            ->where('status', 'pending')
            ->latest()
            ->get();

        // Pesanan yang butuh diproses hari ini
        $todayOrders = Order::with(['service', 'user'])
            ->paymentStatus('paid')
            ->whereIn('status', ['pending', 'diproses'])
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'antrean_cuci'    => $queue('cuci'),
            'antrean_kering'  => $queue('kering'),
            'antrean_lipat'   => $queue('lipat'),
            // Cucian yang tuntas dikerjakan hari ini (siap diambil atau sudah diambil)
            'selesai_hari_ini'=> Order::paymentStatus('paid')->whereIn('status', [Order::STATUS_SIAP_DIAMBIL, Order::STATUS_SELESAI])->whereDate('updated_at', $today)->count(),
        ];

        $services = Service::aktif()->get();

        return view('pegawai.dashboard', compact('activeProcesses', 'todayOrders', 'stats', 'services'));
    }
}
