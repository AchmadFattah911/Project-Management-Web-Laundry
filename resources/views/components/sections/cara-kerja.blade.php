@php
    $paymentMethods = config('laundry.online_payment_methods', []);
@endphp

<section id="cara-kerja" class="py-16 lg:py-24 bg-slate-50 border-b border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-bold uppercase tracking-wider border border-amber-200 mb-3">
                Simpel & Cashless
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                4 Langkah Mudah Mencuci di Laundry Coin
            </h2>
            <p class="text-base text-slate-600 mt-3 leading-relaxed">
                Pesan online atau datang langsung ke outlet. Ikuti 4 tahap praktis ini dan bawa pulang pakaian kering siap pakai.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <!-- Step 1: Pilih Layanan & Masukkan Pakaian -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col">
                <div class="w-10 h-10 rounded-xl bg-sky-600 text-white font-extrabold text-base flex items-center justify-center mb-5 shadow-xs">
                    01
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-3">Pilih Layanan & Masukkan Pakaian</h3>

                <div class="space-y-3 text-xs text-slate-600 leading-relaxed">
                    <div class="rounded-xl bg-sky-50/70 border border-sky-100 p-3">
                        <p class="font-bold text-sky-800 mb-1">Pemesanan Online</p>
                        <p>Masuk ke akun Anda, pilih layanan Drop-Off di bagian Layanan lalu klik <strong>Pesan Sekarang</strong>. Tentukan jumlah paket, buat pesanan, lalu bawa pakaian Anda ke outlet dan serahkan ke petugas.</p>
                    </div>
                    <div class="rounded-xl bg-amber-50/70 border border-amber-100 p-3">
                        <p class="font-bold text-amber-800 mb-1">Langsung di Tempat (Self Laundry)</p>
                        <p>Datang ke outlet, pilih mesin cuci yang berstatus siap pakai, masukkan pakaian sesuai batas kapasitas mesin, lalu tutup pintu mesin dengan rapat.</p>
                    </div>
                </div>

                <div class="mt-auto pt-4">
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 text-[11px] font-semibold text-sky-700">
                        <x-icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Deterjen Otomatis Diberikan</span>
                    </div>
                </div>
            </div>

            <!-- Step 2: Pembayaran Online Cashless -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-slate-900 font-extrabold text-base flex items-center justify-center mb-5 shadow-xs">
                    02
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-3">Pembayaran Online Cashless</h3>

                <ol class="space-y-1.5 text-xs text-slate-600 leading-relaxed list-decimal list-inside mb-4">
                    <li>Pilih metode pembayaran saat membuat pesanan.</li>
                    <li>Bayar sesuai total tagihan yang tertera di detail pesanan.</li>
                    <li>Petugas memverifikasi pembayaran Anda.</li>
                    <li>Status pembayaran berubah menjadi <strong>Lunas</strong> di riwayat pesanan.</li>
                </ol>

                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Metode Pembayaran</p>
                <div class="space-y-2">
                    @foreach ($paymentMethods as $method)
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-bold text-slate-900">{{ $method['label'] }}</p>
                            <p class="text-[11px] text-slate-500 leading-relaxed">{{ $method['description'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-auto pt-4">
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 text-[11px] font-semibold text-amber-700">
                        <x-icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Self Laundry di outlet: bayar langsung di kasir</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Menunggu Estimasi Penyelesaian -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col">
                <div class="w-10 h-10 rounded-xl bg-sky-600 text-white font-extrabold text-base flex items-center justify-center mb-5 shadow-xs">
                    03
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-3">Menunggu Estimasi Penyelesaian Laundry</h3>

                <div class="space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>Setiap layanan memiliki <strong>estimasi waktu selesai</strong> yang tertera di kartu layanan dan di detail pesanan Anda.</p>
                    <p>Untuk layanan Drop-Off, petugas mengerjakan pakaian Anda bertahap (<strong>cuci &rarr; kering &rarr; lipat</strong>) dan setiap tahap bisa dipantau lewat riwayat pesanan atau menu <a href="#lacak" class="font-semibold text-sky-700 hover:underline">Lacak Pesanan</a> dengan kode pesanan.</p>
                    <p>Untuk Self Laundry, Anda bisa bersantai di waiting lounge sambil menunggu mesin selesai.</p>
                </div>

                <div class="mt-auto pt-4">
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 text-[11px] font-semibold text-sky-700">
                        <x-icon name="check" class="w-4 h-4 shrink-0" />
                        <span>Free WiFi & AC Sejuk</span>
                    </div>
                </div>
            </div>

            <!-- Step 4: Pesanan Selesai -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-extrabold text-base flex items-center justify-center mb-5 shadow-xs">
                    04
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-3">Pesanan Selesai, Pakaian Kering Siap Dipakai</h3>

                <div class="space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>Saat status pesanan berubah menjadi <strong>Selesai</strong>, pakaian Anda sudah bersih, wangi, dan kering tuntas. Silakan ambil di outlet dengan menunjukkan kode pesanan.</p>
                    <p>Untuk Self Laundry, pindahkan pakaian ke mesin pengering hingga kering, lalu lipat rapi di meja lipat yang tersedia.</p>
                </div>

                <div class="mt-auto pt-4">
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 text-[11px] font-semibold text-emerald-700">
                        <x-icon name="check" class="w-4 h-4 shrink-0" />
                        <span>100% Kering Tanpa Jemur</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
