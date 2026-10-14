@php
    // Badge keranjang khusus pelanggan (1 query ringan, hanya untuk pelanggan login)
    $cartCount = auth()->user()?->isPelanggan() ? auth()->user()->cartItems()->count() : 0;
@endphp

<nav
    x-data="{ mobileOpen: false, scrolled: false }"
    x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
    :class="scrolled ? 'bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200/80' : 'bg-white border-b border-slate-100'"
    class="sticky top-0 z-40 transition-all duration-200"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-18">
            <!-- Brand Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 rounded-lg">
                <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-xs group-hover:bg-sky-700 transition-colors">
                    <!-- Modern Washer + Coin SVG Icon -->
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <!-- Washer Body -->
                        <rect x="3" y="2" width="18" height="20" rx="3" stroke-width="2" />
                        <!-- Control Panel Line -->
                        <line x1="3" y1="7" x2="21" y2="7" stroke-width="1.5" />
                        <!-- Knobs / Buttons -->
                        <circle cx="7" cy="4.5" r="1" fill="currentColor" />
                        <circle cx="10" cy="4.5" r="1" fill="currentColor" />
                        <!-- Coin Slot -->
                        <rect x="14" y="3.8" width="4" height="1.4" rx="0.5" fill="#F59E0B" stroke="#D97706" stroke-width="0.5" />
                        <!-- Drum Outer -->
                        <circle cx="12" cy="14" r="5" stroke-width="2" />
                        <!-- Drum Inner Waves -->
                        <path d="M10 14c.5-1 1.5-1 2 0s1.5 1 2 0" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight leading-none group-hover:text-sky-600 transition-colors">
                        Laundry <span class="text-sky-600">Coin</span>
                    </span>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-600 mt-0.5">
                        Modern Laundromat
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <div class="hidden md:flex items-center gap-1">
                <a href="{{ route('home') }}#hero" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    Beranda
                </a>
                <a href="{{ route('home') }}#layanan" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    Layanan & Tarif
                </a>
                <a href="{{ route('home') }}#cara-kerja" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    Cara Kerja
                </a>
                <a href="{{ route('home') }}#operasional" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    Area & Jam
                </a>
                <a href="{{ route('home') }}#faq" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    FAQ
                </a>
                <a href="{{ route('home') }}#kontak" class="px-3.5 py-2 text-sm font-medium text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors">
                    Kontak
                </a>
            </div>

            <!-- Desktop Auth Action Buttons -->
            <div class="hidden md:flex items-center gap-2.5">
                @auth
                    @if (auth()->user()->isPelanggan())
                        <a
                            href="{{ route('pelanggan.pesan.index') }}"
                            class="relative inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg shadow-xs transition-all"
                        >
                            <x-icon name="cart" class="w-4 h-4" />
                            <span>Pesan</span>
                            @if ($cartCount > 0)
                                <span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-amber-400 text-slate-900 text-[10px] font-extrabold leading-5 text-center" aria-label="{{ $cartCount }} layanan di keranjang">{{ $cartCount }}</span>
                            @endif
                        </a>
                        <a
                            href="{{ route('pelanggan.akun') }}"
                            class="flex items-center gap-2.5 pl-3 pr-1 py-1 rounded-full border border-slate-200 hover:border-sky-300 hover:bg-sky-50/60 transition-colors"
                            aria-label="Akun Saya"
                        >
                            <span class="text-sm font-semibold text-slate-800 max-w-[10rem] truncate">{{ auth()->user()->name }}</span>
                            <span class="w-8 h-8 rounded-full bg-sky-600 text-white flex items-center justify-center text-xs font-extrabold">{{ auth()->user()->initials() }}</span>
                        </a>
                    @else
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg shadow-xs transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    @endif
                @else
                    <a
                        href="{{ route('login') }}"
                        class="px-3.5 py-2 text-sm font-semibold text-slate-700 hover:text-sky-600 rounded-lg hover:bg-slate-50 transition-colors"
                    >
                        Masuk
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg shadow-xs transition-all active:scale-[0.98]"
                    >
                        <span>Daftar Akun</span>
                    </a>
                @endauth
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="flex items-center md:hidden">
                <button
                    type="button"
                    x-on:click="mobileOpen = !mobileOpen"
                    class="p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    aria-label="Toggle navigation"
                >
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Drawer Menu -->
    <div
        x-show="mobileOpen"
        x-collapse
        class="md:hidden border-t border-slate-100 bg-white px-4 pt-3 pb-6 space-y-2"
        style="display: none;"
    >
        <a href="{{ route('home') }}#hero" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            Beranda
        </a>
        <a href="{{ route('home') }}#layanan" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            Layanan & Tarif
        </a>
        <a href="{{ route('home') }}#cara-kerja" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            Cara Kerja
        </a>
        <a href="{{ route('home') }}#operasional" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            Area & Jam
        </a>
        <a href="{{ route('home') }}#faq" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            FAQ
        </a>
        <a href="{{ route('home') }}#kontak" x-on:click="mobileOpen = false" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 rounded-lg">
            Kontak
        </a>

        <div class="pt-4 border-t border-slate-100 flex flex-col gap-2">
            @auth
                @if (auth()->user()->isPelanggan())
                    <a
                        href="{{ route('pelanggan.pesan.index') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-sky-700 bg-sky-50 border border-sky-200 rounded-lg"
                    >
                        <x-icon name="cart" class="w-4 h-4" />
                        Pesan Layanan{{ $cartCount > 0 ? " ({$cartCount} di keranjang)" : '' }}
                    </a>
                @endif
                <a
                    href="{{ auth()->user()->isPelanggan() ? route('pelanggan.akun') : route('dashboard') }}"
                    class="w-full text-center px-4 py-2.5 text-sm font-semibold text-white bg-sky-600 rounded-lg"
                >
                    {{ auth()->user()->isPelanggan() ? 'Akun Saya' : 'Dashboard Saya' }}
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="w-full text-center px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded-lg"
                >
                    Masuk
                </a>
                <a
                    href="{{ route('register') }}"
                    class="w-full text-center px-4 py-2.5 text-sm font-semibold text-white bg-sky-600 rounded-lg"
                >
                    Daftar Akun Baru
                </a>
            @endauth
        </div>
    </div>
</nav>
