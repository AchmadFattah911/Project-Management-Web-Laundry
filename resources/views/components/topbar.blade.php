@props([
    'title' => 'Dashboard',
])

@php
    $user = auth()->user() ?? (object)['name' => 'Admin User', 'role' => 'admin'];
@endphp

<header class="bg-white border-b border-slate-200/80 sticky top-0 z-30">
    <div class="px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4">
        <!-- Left: Mobile Menu Toggle & Page Title -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                x-on:click="sidebarOpen = true"
                class="lg:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                aria-label="Buka menu navigasi"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight leading-tight">
                    {{ $title }}
                </h1>
            </div>
        </div>

        <!-- Right: Status Pill & Profile Dropdown -->
        <div class="flex items-center gap-3">
            <!-- Machine Status Badge -->
            <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Sistem Laundry Aktif</span>
            </div>

            <!-- Profile Dropdown Menu -->
            <div x-data="{ userMenu: false }" class="relative" x-on:click.outside="userMenu = false">
                <button
                    type="button"
                    x-on:click="userMenu = !userMenu"
                    class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500"
                >
                    <div class="w-8 h-8 rounded-full bg-sky-600 text-white font-bold text-xs flex items-center justify-center">
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </div>
                    <span class="hidden md:inline-block text-xs font-semibold text-slate-700 max-w-[120px] truncate">
                        {{ $user->name ?? 'User' }}
                    </span>
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Popover -->
                <div
                    x-show="userMenu"
                    x-transition:enter="ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg border border-slate-200 py-1.5 z-50 text-xs"
                    style="display: none;"
                >
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="font-semibold text-slate-900 truncate">{{ $user->name ?? 'User' }}</p>
                        <p class="text-[11px] text-slate-500 truncate">{{ $user->email ?? '' }}</p>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-sky-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Pengaturan Profil</span>
                    </a>

                    <div class="border-t border-slate-100 my-1"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-red-600 hover:bg-red-50 text-left">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Keluar (Logout)</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
