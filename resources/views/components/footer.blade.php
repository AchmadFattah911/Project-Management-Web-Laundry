<footer class="bg-slate-900 text-slate-300 border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
            <!-- Brand Column -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow-xs">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
                        <span class="text-xl font-extrabold text-white tracking-tight">Laundry <span class="text-sky-400">Coin</span></span>
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-400">Modern Laundromat & Service</p>
                    </div>
                </div>

                <p class="text-sm text-slate-400 leading-relaxed max-w-sm">
                    Laundromat modern terpercaya. Menghadirkan solusi cuci mandiri berkecepatan tinggi, higienis, serta layanan drop-off kiloan dan satuan dengan standar kebersihan terbaik.
                </p>

                <!-- Badges -->
                <div class="flex flex-wrap gap-2 pt-1">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 text-slate-300 text-xs border border-slate-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Mesin Siap Pakai
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 text-amber-300 text-xs border border-slate-700">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        Pembayaran Cashless
                    </span>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Navigasi Cepat</h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="{{ route('home') }}#hero" class="hover:text-sky-400 transition-colors">Beranda</a></li>
                    <li><a href="{{ route('home') }}#layanan" class="hover:text-sky-400 transition-colors">Daftar Layanan</a></li>
                    <li><a href="{{ route('home') }}#cara-kerja" class="hover:text-sky-400 transition-colors">Cara Kerja</a></li>
                    <li><a href="{{ route('home') }}#faq" class="hover:text-sky-400 transition-colors">Pertanyaan Umum (FAQ)</a></li>
                    <li><a href="{{ route('home') }}#kontak" class="hover:text-sky-400 transition-colors">Hubungi Kami</a></li>
                </ul>
            </div>

            <!-- Jam Operasional -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Jam Operasional</h4>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li class="flex items-start justify-between border-b border-slate-800 pb-2">
                        <span>Senin - Jumat</span>
                        <span class="font-medium text-slate-200">{{ config('laundry.operating_hours.weekday', '07:00 - 22:00') }}</span>
                    </li>
                    <li class="flex items-start justify-between border-b border-slate-800 pb-2">
                        <span>Sabtu - Minggu</span>
                        <span class="font-medium text-slate-200">{{ config('laundry.operating_hours.weekend', '07:00 - 22:00') }}</span>
                    </li>
                    <li class="pt-1 text-xs text-amber-400 flex items-center gap-1.5 font-medium">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                        {{ config('laundry.operating_hours.notes', 'Buka Setiap Hari Termasuk Libur') }}
                    </li>
                </ul>
            </div>

            <!-- Kontak & Alamat -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4">Outlet & Kontak</h4>
                <ul class="space-y-2.5 text-sm text-slate-400">
                    <li class="flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-sky-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ config('laundry.address', '[ISI ALAMAT TOKO LENGKAP]') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>{{ config('laundry.phone', '[ISI NOMOR HP/TELEPON]') }}</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ config('laundry.email', '[ISI EMAIL TOKO]') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Copyright -->
        <div class="mt-12 pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <p>&copy; {{ date('Y') }} Laundry Coin. Seluruh Hak Cipta Dilindungi.</p>
            <div class="flex items-center gap-6">
                <a href="#" class="hover:text-slate-400 transition-colors">Kebijakan Privasi</a>
                <a href="#" class="hover:text-slate-400 transition-colors">Syarat & Ketentuan</a>
                <a href="#" class="hover:text-slate-400 transition-colors">Bantuan Pelanggan</a>
            </div>
        </div>
    </div>
</footer>
