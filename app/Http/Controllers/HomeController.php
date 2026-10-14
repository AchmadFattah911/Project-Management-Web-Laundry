<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    /**
     * Tampilkan landing page publik dengan katalog layanan aktif.
     * Pemesanan dilakukan di halaman terpisah (/pesan).
     */
    public function index(): View
    {
        return view('home', [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
            'services'    => Service::aktif()->get(),
        ]);
    }
}
