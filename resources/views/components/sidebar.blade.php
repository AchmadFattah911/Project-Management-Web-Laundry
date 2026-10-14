@php
    $user = auth()->user() ?? (object)['name' => 'Pengguna', 'role' => 'admin', 'email' => ''];
    $role = $user->role instanceof \App\Enums\UserRole ? $user->role->value : strtolower($user->role ?? 'admin');

    $adminLinks = [
        ['name' => 'Ringkasan Dashboard', 'route' => 'admin.dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['name' => 'Manajemen User', 'route' => 'admin.users.index', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['name' => 'Manajemen Order', 'route' => 'admin.orders.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['name' => 'Layanan Laundry', 'route' => 'admin.services.index', 'icon' => 'M3 7h18M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4a3 3 0 016 0v3'],
    ];

    $pegawaiLinks = [
        ['name' => 'Antrean Pengerjaan', 'route' => 'pegawai.dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
        ['name' => 'Semua Pesanan', 'route' => 'pegawai.orders.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
    ];

    $pelangganLinks = [
        ['name' => 'Akun Saya', 'route' => 'pelanggan.akun', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['name' => 'Riwayat Cucian', 'route' => 'pelanggan.orders.index', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ];

    $links = match ($role) {
        'pegawai' => $pegawaiLinks,
        'pelanggan' => $pelangganLinks,
        default => $adminLinks,
    };
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
                $isActive = Route::has($link['route']) && request()->routeIs($link['route']);
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
