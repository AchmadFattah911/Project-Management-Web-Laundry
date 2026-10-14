@props([
    'services' => null,
])

@php
    $servicesList = collect($services ?? []);
    $selfServices = $servicesList->filter(fn($s) => strcasecmp($s->category, 'Self Service') === 0);
    $nonSelfServices = $servicesList->filter(fn($s) => strcasecmp($s->category, 'Non-Self Service') === 0);

    // Tombol pesan online: tamu (diarahkan login) & pelanggan. Admin/pegawai memesan lewat panel kasir.
    $authUser = auth()->user();
    $isPelanggan = $authUser?->isPelanggan() ?? false;
@endphp

<section id="layanan" class="py-16 lg:py-24 bg-white border-b border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-wider border border-sky-200 mb-3">
                Tarif Transparan & Bersahabat
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                Pilihan Layanan Laundry Coin
            </h2>
            <p class="text-base text-slate-600 mt-3 leading-relaxed">
                Cuci sendiri langsung di outlet dengan layanan self laundry, atau pesan online layanan drop-off dan serahkan cucian Anda pada staf kami.
            </p>
        </div>

        <!-- Kotak Info Kapasitas Mesin -->
        <div class="mb-14 rounded-2xl bg-amber-50/70 border border-amber-200 p-6 sm:p-7 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-amber-200/70 pb-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500 text-slate-900 flex items-center justify-center font-bold shrink-0">
                        <x-icon name="washer" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Ketentuan & Batas Kapasitas Mesin Cuci</h3>
                        <p class="text-xs text-slate-600">Pastikan muatan cucian sesuai anjuran agar hasil cuci & pengeringan optimal 100%.</p>
                    </div>
                </div>
                <span class="inline-flex self-start sm:self-auto items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-200 text-amber-900">
                    Kapasitas Terukur
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <div class="bg-white/80 p-3.5 rounded-xl border border-amber-200/60 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold shrink-0">1</span>
                    <div>
                        <p class="font-bold text-slate-900">Pakaian Biasa</p>
                        <p class="text-slate-600 font-semibold text-amber-900">Maksimal 6 KG / mesin</p>
                    </div>
                </div>
                <div class="bg-white/80 p-3.5 rounded-xl border border-amber-200/60 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold shrink-0">2</span>
                    <div>
                        <p class="font-bold text-slate-900">Sprei / Selimut Tipis</p>
                        <p class="text-slate-600 font-semibold text-amber-900">Maksimal 3 KG / mesin</p>
                    </div>
                </div>
                <div class="bg-white/80 p-3.5 rounded-xl border border-amber-200/60 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold shrink-0">3</span>
                    <div>
                        <p class="font-bold text-slate-900">Bedcover</p>
                        <p class="text-slate-600 font-semibold text-amber-900">Maksimal 1 Pcs / mesin</p>
                    </div>
                </div>
                <div class="bg-white/80 p-3.5 rounded-xl border border-amber-200/60 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold shrink-0">4</span>
                    <div>
                        <p class="font-bold text-slate-900">Mesin Besar</p>
                        <p class="text-slate-600 font-semibold text-amber-900">Maksimal 12 KG / mesin</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRUP 1: Self Service -->
        <div class="mb-16">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-3 h-8 bg-sky-600 rounded-full"></span>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900">1. Layanan Self Laundry (Cuci Mandiri di Outlet)</h3>
                    <p class="text-xs text-slate-500">Cuci & keringkan pakaian sendiri secara fleksibel, cepat, dan higienis langsung di outlet kami.</p>
                </div>
            </div>

            @if ($selfServices->isEmpty())
                <x-empty-state title="Layanan Self Service Belum Tersedia" description="Saat ini belum ada layanan self service aktif yang dapat ditampilkan." />
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($selfServices as $service)
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs hover:border-sky-300 hover:shadow-md transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center">
                                        <x-icon name="washer" class="w-5 h-5" />
                                    </div>
                                    <x-badge variant="info" size="sm">Self Laundry</x-badge>
                                </div>

                                <h4 class="text-lg font-bold text-slate-900 leading-snug">{{ $service->name }}</h4>
                                <p class="text-xs text-slate-500 mt-1 mb-4">{{ $service->description ?? 'Layanan cuci mandiri langsung di outlet.' }}</p>

                                <div class="border-y border-slate-100 py-3 my-3">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-extrabold text-slate-900">Rp {{ number_format($service->price, 0, ',', '.') }}</span>
                                        <span class="text-xs text-slate-500 font-semibold">/ {{ $service->unit }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-2 text-xs text-sky-700 font-medium">
                                        <x-icon name="clock" class="w-3.5 h-3.5 shrink-0" />
                                        <span>Estimasi: {{ $service->estimate }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 mt-2">
                                <div class="flex items-center justify-center gap-2 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2.5 text-xs font-bold text-amber-800">
                                    <x-icon name="map-pin" class="w-4 h-4 shrink-0" />
                                    <span>Menu Khusus Langsung Ditempat</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- GRUP 2: Non-Self Service (Titip) -->
        <div>
            <div class="flex items-center gap-3 mb-6">
                <span class="w-3 h-8 bg-slate-800 rounded-full"></span>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900">2. Layanan Non-Self Service (Drop-Off / Titip Petugas)</h3>
                    <p class="text-xs text-slate-500">Cucian Anda ditangani langsung oleh petugas terlatih kami hingga bersih, rapi, dan siap diambil.</p>
                </div>
            </div>

            @if ($nonSelfServices->isEmpty())
                <x-empty-state title="Layanan Non-Self Service Belum Tersedia" description="Saat ini belum ada layanan non-self service aktif yang dapat ditampilkan." />
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($nonSelfServices as $service)
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs hover:border-slate-300 hover:shadow-md transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                                        <x-icon name="truck" class="w-5 h-5" />
                                    </div>
                                    <x-badge variant="neutral" size="sm">Drop-Off Titip</x-badge>
                                </div>

                                <h4 class="text-lg font-bold text-slate-900 leading-snug">{{ $service->name }}</h4>
                                <p class="text-xs text-slate-500 mt-1 mb-4">{{ $service->description ?? 'Layanan titip cuci staf profesional.' }}</p>

                                <div class="border-y border-slate-100 py-3 my-3">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-extrabold text-slate-900">Rp {{ number_format($service->price, 0, ',', '.') }}</span>
                                        <span class="text-xs text-slate-500 font-semibold">/ {{ $service->unit }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-2 text-xs text-slate-700 font-medium">
                                        <x-icon name="clock" class="w-3.5 h-3.5 shrink-0" />
                                        <span>Estimasi: {{ $service->estimate }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 mt-2">
                                @if ($isPelanggan)
                                    <x-button href="{{ route('pelanggan.pesan.index', ['layanan' => $service->slug]) }}" size="sm" class="w-full">
                                        Pesan Sekarang
                                    </x-button>
                                @elseif (! $authUser)
                                    <x-button href="{{ route('pelanggan.orders.start', $service) }}" size="sm" class="w-full">
                                        Pesan Sekarang
                                    </x-button>
                                @else
                                    <p class="text-center text-[11px] font-semibold text-slate-500">Pemesanan online khusus akun pelanggan</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
