@php
    $user = auth()->user() ?? (object)['name' => 'Pengguna', 'role' => 'admin', 'email' => ''];
    $role = $user->role instanceof \App\Enums\UserRole ? $user->role->value : strtolower($user->role ?? 'admin');

    $adminLinks = [
        ['name' => 'Ringkasan Dashboard', 'route' => 'admin.dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['name' => 'Manajemen User', 'route' => 'admin.users.index', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['name' => 'Manajemen Order', 'route' => 'admin.orders.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['name' => 'Perlu Refund', 'route' => 'admin.refunds.index', 'icon' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'],
        ['name' => 'Layanan Laundry', 'route' => 'admin.services.index', 'icon' => 'M3 7h18M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4a3 3 0 016 0v3'],
    ];

    $pegawaiLinks = [
        ['name' => 'Antrean Pengerjaan', 'route' => 'pegawai.dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['name' => 'Semua Pesanan', 'route' => 'pegawai.orders.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['name' => 'Laporan Keuangan', 'route' => 'pegawai.keuangan.index', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ];

    $links = match ($role) {
        'pegawai' => $pegawaiLinks,
        // Pelanggan tidak memakai dashboard (lihat halaman Akun / layouts.mobile)
        'pelanggan' => [],
        default => $adminLinks,
    };

    // Menu panel kasir & keuangan: tampil sesuai permission (config/permissions.php), sama untuk admin & pegawai
    $panelLinks = [
        ['name' => 'Transaksi', 'route' => 'panel.transaksi.index', 'match' => 'panel.transaksi.*', 'can' => 'transaksi.outlet.view', 'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
        ['name' => 'Pelanggan', 'route' => 'panel.pelanggan.index', 'match' => 'panel.pelanggan.*', 'can' => 'pelanggan.view', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['name' => 'Kas & Shift', 'route' => 'panel.shift.index', 'match' => 'panel.shift.*', 'can' => 'shift.view_own', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['name' => 'Pengeluaran', 'route' => 'panel.pengeluaran.index', 'match' => 'panel.pengeluaran.*', 'can' => 'pengeluaran.view_own', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
        ['name' => 'Persetujuan Void', 'route' => 'panel.void.index', 'match' => 'panel.void.*', 'can' => 'transaksi.void.approve', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
        ['name' => 'Laporan Keuangan', 'route' => 'panel.laporan.index', 'match' => 'panel.laporan.*', 'can' => 'laporan.view', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['name' => 'Ekspor Saya', 'route' => 'panel.ekspor.index', 'match' => 'panel.ekspor.*', 'can' => 'laporan.export', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
        ['name' => 'Tutup Buku', 'route' => 'panel.periode.index', 'match' => 'panel.periode.*', 'can' => 'periode.close', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
    ];

    $links = array_merge($links, array_values(array_filter(
        $panelLinks,
        fn ($link) => Route::has($link['route']) && auth()->user()?->can($link['can'])
    )));
@endphp

<!-- Sidebar Backdrop (Mobile Only) -->
<div
    x-show="sidebarOpen"
    x-on:click="sidebarOpen = false"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden backdrop-blur-xs"
    style="display: none;"
></div>

<!-- Main Sidebar Container -->
<aside
    class="fixed top-0 left-0 h-full w-64 bg-slate-900 text-white flex flex-col z-50 transition-transform duration-300 ease-in-out lg:translate-x-0 border-r border-slate-800"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    <!-- Close Button (Mobile Only) -->
    <button
        type="button"
        x-on:click="sidebarOpen = false"
        class="absolute top-4 right-4 lg:hidden p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white"
        aria-label="Tutup menu"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    <!-- Sidebar Header / Brand Logo -->
    <div class="px-6 py-6 border-b border-slate-800 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-xs shrink-0">
            <x-icon name="washer" class="w-6 h-6" />
        </div>
        <div class="flex flex-col overflow-hidden">
            <a href="{{ route('home') }}" class="font-extrabold text-lg tracking-tight text-white leading-none hover:text-sky-400 transition-colors">
                Laundry <span class="text-sky-400">Coin</span>
            </a>
            <div class="flex items-center gap-1.5 mt-1.5">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">
                    Panel {{ $role }}
                </span>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
        @foreach ($links as $link)
            @php
                $isActive = Route::has($link['route']) && request()->routeIs($link['match'] ?? $link['route']);
                $url = Route::has($link['route']) ? route($link['route']) : '#';
            @endphp
            <a
                href="{{ $url }}"
                class="relative flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors group {{ $isActive ? 'bg-sky-600 text-white font-semibold shadow-xs' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
            >
                @if ($isActive)
                    <div class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-amber-400 rounded-r-md"></div>
                @endif
                <svg class="w-5 h-5 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}" />
                </svg>
                <span class="truncate">{{ $link['name'] }}</span>
            </a>
        @endforeach

        <div class="pt-4 mt-4 border-t border-slate-800">
            <a
                href="{{ route('home') }}"
                target="_blank"
                class="flex items-center gap-3 px-3.5 py-2 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-800 hover:text-slate-200 transition-colors"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <span>Lihat Beranda Publik</span>
            </a>
        </div>
    </nav>

    <!-- Bottom User & Logout -->
    <div class="p-4 border-t border-slate-800 space-y-3 bg-slate-900/50">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-800 transition-colors group">
            <div class="w-9 h-9 rounded-full bg-sky-600/30 text-sky-400 border border-sky-500/30 flex items-center justify-center font-bold text-xs shrink-0">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div class="flex-col min-w-0 flex-1">
                <p class="text-xs font-semibold text-white truncate">{{ $user->name ?? 'User' }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ $user->email ?? '' }}</p>
            </div>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white bg-slate-800 hover:bg-red-600/80 rounded-lg transition-colors cursor-pointer"
            >
                <x-icon name="logout" class="w-4 h-4" />
                <span>Keluar (Logout)</span>
            </button>
        </form>
    </div>
</aside>
