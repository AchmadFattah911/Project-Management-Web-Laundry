<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - Laundry Coin' : 'Masuk / Daftar - Laundry Coin' }}</title>

    <!-- Fonts: Plus Jakarta Sans & DM Sans via Bunny Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284C7'><rect x='3' y='2' width='18' height='20' rx='3'/><circle cx='12' cy='14' r='5' fill='white'/></svg>">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased selection:bg-sky-200 selection:text-sky-900">
    <div class="min-h-screen flex">
        <!-- Left Side Brand Banner (Desktop Only) -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-sky-900 via-sky-800 to-slate-900 text-white p-12 flex-col justify-between relative overflow-hidden border-r border-sky-800/40">
            <!-- Subtle Background Water Rings Pattern -->
            <div class="absolute -right-24 -bottom-24 w-96 h-96 rounded-full border border-sky-500/10 pointer-events-none"></div>
            <div class="absolute -right-12 -bottom-12 w-72 h-72 rounded-full border border-sky-500/15 pointer-events-none"></div>
            <div class="absolute top-1/4 -left-20 w-80 h-80 rounded-full border border-amber-500/10 pointer-events-none"></div>

            <!-- Top Brand Logo -->
            <div class="relative z-10 flex items-center gap-3">
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-sky-600/90 border border-sky-400/30 text-white flex items-center justify-center shadow-md">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="2" width="18" height="20" rx="3" stroke-width="2" />
                            <line x1="3" y1="7" x2="21" y2="7" stroke-width="1.5" />
                            <circle cx="7" cy="4.5" r="1" fill="currentColor" />
                            <circle cx="10" cy="4.5" r="1" fill="currentColor" />
                            <rect x="14" y="3.8" width="4" height="1.4" rx="0.5" fill="#F59E0B" stroke="#D97706" stroke-width="0.5" />
                            <circle cx="12" cy="14" r="5" stroke-width="2" />
                            <path d="M10 14c.5-1 1.5-1 2 0s1.5 1 2 0" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-2xl font-extrabold text-white tracking-tight">Laundry <span class="text-sky-400">Coin</span></span>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-amber-400">Self-Service & Express</p>
                    </div>
                </a>
            </div>

            <!-- Center Illustration & Copy -->
            <div class="relative z-10 my-auto py-10 max-w-md">
                <!-- Custom Modern Coin Laundromat Machine SVG -->
                <div class="w-44 h-48 mx-auto mb-8 relative flex items-center justify-center">
                    <svg class="w-full h-full drop-shadow-2xl" viewBox="0 0 160 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Machine Body -->
                        <rect x="10" y="8" width="140" height="164" rx="18" fill="#0F172A" stroke="#38BDF8" stroke-width="3" />
                        <!-- Top Bevel -->
                        <rect x="16" y="14" width="128" height="34" rx="10" fill="#1E293B" stroke="#334155" stroke-width="1.5" />
                        <!-- LED Display -->
                        <rect x="26" y="22" width="42" height="18" rx="4" fill="#0284C7" />
                        <text x="32" y="35" font-family="monospace" font-size="11" font-weight="bold" fill="#E0F2FE">45 MIN</text>
                        <!-- Coin Slot with Gold Glow -->
                        <rect x="78" y="25" width="22" height="4" rx="2" fill="#F59E0B" stroke="#FDE68A" stroke-width="1" />
                        <circle cx="116" cy="27" r="6" fill="#334155" />
                        <circle cx="116" cy="27" r="3" fill="#0EA5E9" />
                        <!-- Porthole Door Ring -->
                        <circle cx="80" cy="108" r="46" fill="#1E293B" stroke="#64748B" stroke-width="3" />
                        <!-- Glass Window -->
                        <circle cx="80" cy="108" r="36" fill="#0369A1" fill-opacity="0.3" stroke="#38BDF8" stroke-width="2" />
                        <!-- Drum Interior -->
                        <g class="animate-drum-spin origin-[80px_108px]">
                            <circle cx="80" cy="108" r="28" stroke="#7DD3FC" stroke-width="2" stroke-dasharray="8 6" opacity="0.8" />
                            <circle cx="80" cy="108" r="18" stroke="#BAE6FD" stroke-width="2" stroke-dasharray="4 4" opacity="0.6" />
                            <path d="M72 108c3-4 7-4 10 0s7 4 10 0" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                        </g>
                        <!-- Golden Coin Floating In -->
                        <g transform="translate(112, 4) scale(0.9)">
                            <circle cx="16" cy="16" r="14" fill="#F59E0B" stroke="#FEF3C7" stroke-width="2" />
                            <circle cx="16" cy="16" r="10" fill="#D97706" />
                            <text x="12" y="20" font-family="sans-serif" font-size="11" font-weight="extrabold" fill="#FFFBEB">C</text>
                        </g>
                    </svg>
                </div>

                <h2 class="text-2xl font-bold tracking-tight text-white mb-3">
                    Cuci Bersih, Cepat & Tanpa Antri
                </h2>
                <p class="text-slate-300 text-sm leading-relaxed mb-6">
                    Akses akun Laundry Coin Anda untuk memantau status cucian secara real-time, melihat riwayat transaksi koin, dan menikmati kemudahan layanan antar-jemput.
                </p>

                <!-- Value Proposition Highlights -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-3 text-xs text-slate-200">
                        <div class="w-6 h-6 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0 border border-sky-500/30">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span>Mesin komersial kapasitas besar berkecepatan tinggi</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-200">
                        <div class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 border border-amber-500/30">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span>Pembayaran koin atau QRIS fleksibel tanpa ribet</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-200">
                        <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span>Buka setiap hari 07:00 - 22:00 dengan lounge nyaman</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Left Info -->
            <div class="relative z-10 text-xs text-slate-400 flex items-center justify-between border-t border-sky-800/60 pt-6">
                <span>&copy; {{ date('Y') }} Laundry Coin</span>
                <span>Layanan Pelanggan: 0812-3456-7890</span>
            </div>
        </div>

        <!-- Right Side Form Container -->
        <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-20 xl:px-24 bg-white overflow-y-auto">
            <div class="mx-auto w-full max-w-md">
                <!-- Mobile Only Brand Header -->
                <div class="lg:hidden mb-8 text-center">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-xs">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect x="3" y="2" width="18" height="20" rx="3" stroke-width="2" />
                                <circle cx="12" cy="14" r="5" stroke-width="2" />
                            </svg>
                        </div>
                        <span class="text-xl font-extrabold text-slate-900 tracking-tight">Laundry <span class="text-sky-600">Coin</span></span>
                    </a>
                </div>

                <!-- Back to Home link -->
                <div class="mb-6">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-sky-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Kembali ke Beranda</span>
                    </a>
                </div>

                <!-- Form Card / Slot -->
                <div>
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
