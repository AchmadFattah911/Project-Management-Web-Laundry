<script setup>
import { ref } from 'vue';

const active = ref(null);

const toggleFaq = (id) => {
    active.value = active.value === id ? null : id;
};
</script>

<template>
    <div id="layanan" class="font-sans text-text bg-bg overflow-x-hidden">
        <!-- 1. Pilihan Layanan (Kategori Section) -->
        <div id="kategori" class="py-24 px-8 max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-4 tracking-tight">Pilih <span class="text-primary">Layanan Terbaik</span> Untuk Pakaian Anda</h2>
                <p class="text-muted max-w-2xl mx-auto">Kami menyediakan berbagai kategori layanan profesional yang dikerjakan dengan hati dan teknologi terkini.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Cuci Kiloan -->
                <div class="bg-white rounded-2xl p-8 border border-border shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all duration-300 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center text-primary mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Cuci Kiloan</h3>
                    <p class="text-sm text-muted mb-6">Pilihan praktis & hemat untuk pakaian harian keluarga Anda.</p>
                    <ul class="text-sm text-left w-full space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Cuci + Kering + Lipat</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Estimasi 2-3 Hari</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Higienis Per Pelanggan</li>
                    </ul>
                    <div class="w-full pt-6 border-t border-border">
                        <div class="text-primary font-bold text-2xl mb-4">Rp8.000<span class="text-xs text-muted font-normal"> /kg</span></div>
                        <button class="w-full py-3 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover transition-colors">Pilih Layanan</button>
                    </div>
                </div>

                <!-- Cuci Satuan -->
                <div class="bg-white rounded-2xl p-8 border border-border shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all duration-300 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Cuci Satuan</h3>
                    <p class="text-sm text-muted mb-6">Penanganan khusus untuk pakaian berharga & material sensitif.</p>
                    <ul class="text-sm text-left w-full space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Jas, Dress, Kebaya, dll</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Teknik Finishing Khusus</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Packing Premium</li>
                    </ul>
                    <div class="w-full pt-6 border-t border-border">
                        <div class="text-primary font-bold text-2xl mb-4"><span class="text-xs text-muted font-normal">Mulai </span>Rp15.000<span class="text-xs text-muted font-normal"> /pcs</span></div>
                        <button class="w-full py-3 rounded-lg border-2 border-primary text-primary font-semibold hover:bg-primary hover:text-white transition-all">Pilih Layanan</button>
                    </div>
                </div>

                <!-- Bedcover / Karpet -->
                <div class="bg-white rounded-2xl p-8 border border-border shadow-sm hover:shadow-xl hover:-translate-y-2 transition-all duration-300 flex flex-col items-center text-center group">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Bedcover & Karpet</h3>
                    <p class="text-sm text-muted mb-6">Membersihkan debu & tungau pada perlengkapan tidur Anda.</p>
                    <ul class="text-sm text-left w-full space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Deep Cleaning Method</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Sterilisasi Kuman</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Wangi Extra Segar</li>
                    </ul>
                    <div class="w-full pt-6 border-t border-border">
                        <div class="text-primary font-bold text-2xl mb-4"><span class="text-xs text-muted font-normal">Mulai </span>Rp25.000<span class="text-xs text-muted font-normal"> /pcs</span></div>
                        <button class="w-full py-3 rounded-lg border-2 border-primary text-primary font-semibold hover:bg-primary hover:text-white transition-all">Pilih Layanan</button>
                    </div>
                </div>

                <!-- Express Service -->
                <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 shadow-xl hover:-translate-y-2 transition-all duration-300 flex flex-col items-center text-center group relative overflow-hidden">
                    <div class="absolute top-4 right-4 bg-secondary text-primary font-bold text-[10px] uppercase px-3 py-1 rounded-full animate-pulse">Cepat</div>
                    <div class="w-20 h-20 bg-secondary/10 rounded-full flex items-center justify-center text-secondary mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Express Service</h3>
                    <p class="text-sm text-gray-400 mb-6">Solusi cerdas saat Anda butuh pakaian bersih dalam waktu singkat.</p>
                    <ul class="text-sm text-left w-full space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-2 text-gray-300"><svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Selesai Same-Day</li>
                        <li class="flex items-center gap-2 text-gray-300"><svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Prioritas Utama</li>
                        <li class="flex items-center gap-2 text-gray-300"><svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Pick-up Segera</li>
                    </ul>
                    <div class="w-full pt-6 border-t border-gray-800">
                        <div class="text-secondary font-bold text-2xl mb-4">+ Biaya Tambahan</div>
                        <button class="w-full py-3 rounded-lg bg-secondary text-primary font-bold hover:brightness-110 active:scale-95 transition-all">Pesan Kilat</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Daftar Harga (Pricing Table + Add-ons) -->
        <div id="pricing" class="py-24 px-8 bg-white border-t border-border">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4 tracking-tight">Daftar Harga <span class="text-primary">Eksklusif</span></h2>
                    <p class="text-muted">Transparansi harga untuk setiap sentuhan kebersihan pakaian Anda.</p>
                </div>

                <!-- Main Table -->
                <div class="overflow-hidden rounded-2xl border border-border shadow-sm mb-16">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th class="px-6 py-4 font-bold text-sm uppercase tracking-wider">Layanan Utama</th>
                                <th class="px-6 py-4 font-bold text-sm uppercase tracking-wider">Satuan</th>
                                <th class="px-6 py-4 font-bold text-sm uppercase tracking-wider text-right">Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr class="hover:bg-container transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">Cuci Kiloan (Reguler)</td>
                                <td class="px-6 py-4 text-muted">per Kilogram</td>
                                <td class="px-6 py-4 font-bold text-primary text-right text-lg">Rp8.000</td>
                            </tr>
                            <tr class="hover:bg-container transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">Cuci Satuan (Jas/Jaket)</td>
                                <td class="px-6 py-4 text-muted">per Potong</td>
                                <td class="px-6 py-4 font-bold text-primary text-right text-lg">Rp15.000+</td>
                            </tr>
                            <tr class="hover:bg-container transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">Bedcover / Karpet</td>
                                <td class="px-6 py-4 text-muted">per Potong</td>
                                <td class="px-6 py-4 font-bold text-primary text-right text-lg">Rp25.000+</td>
                            </tr>
                            <tr class="hover:bg-container transition-colors bg-secondary/5">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <div class="flex items-center gap-2">
                                        Express Service Same-Day
                                        <span class="bg-secondary text-primary text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Kilat</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-muted">per Kilogram</td>
                                <td class="px-6 py-4 font-bold text-primary text-right text-lg">Hubungi Kami</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add-ons Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-container p-6 rounded-xl border border-border flex items-start gap-4 hover:border-primary/50 transition-all group">
                        <div class="w-12 h-12 bg-secondary/20 rounded-lg flex items-center justify-center text-primary flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900">Setrika Saja</h4>
                            <p class="text-xs text-muted mt-1 leading-relaxed">Pakaian rapi tanpa repot mencuci kembali.</p>
                        </div>
                    </div>
                    <div class="bg-container p-6 rounded-xl border border-border flex items-start gap-4 hover:border-primary/50 transition-all group">
                        <div class="w-12 h-12 bg-secondary/20 rounded-lg flex items-center justify-center text-primary flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900">Parfum Premium</h4>
                            <p class="text-xs text-muted mt-1 leading-relaxed">Pilihan aroma mewah yang tahan lebih lama.</p>
                        </div>
                    </div>
                    <div class="bg-container p-6 rounded-xl border border-border flex items-start gap-4 hover:border-primary/50 transition-all group">
                        <div class="w-12 h-12 bg-secondary/20 rounded-lg flex items-center justify-center text-primary flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900">Anti-Bakteri</h4>
                            <p class="text-xs text-muted mt-1 leading-relaxed">Perlindungan ekstra dari kuman & virus.</p>
                        </div>
                    </div>
                    <div class="bg-container p-6 rounded-xl border border-border flex items-start gap-4 hover:border-primary/50 transition-all group">
                        <div class="w-12 h-12 bg-secondary/20 rounded-lg flex items-center justify-center text-primary flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900">Packing Premium</h4>
                            <p class="text-xs text-muted mt-1 leading-relaxed">Kemasan eksklusif & aman untuk dibawa.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Area Layanan (Operational & Area) -->
        <div id="area" class="py-24 px-8 bg-container border-y border-border">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-16">
                <div class="lg:w-1/2">
                    <div class="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center text-primary mb-8 transform -rotate-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-6">Area Layanan & <span class="text-primary">Operasional</span></h2>
                    <p class="text-muted mb-8 leading-relaxed">Kami melayani dengan sepenuh hati untuk wilayah <span class="font-bold text-gray-900">Samarinda</span> dan sekitarnya. Kenyamanan Anda adalah prioritas kami.</p>

                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-success/20 text-success rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900">Wilayah Cakupan</h4>
                                <p class="text-sm text-muted">Samarinda Kota, Samarinda Ulu, Samarinda Ilir, dan sekitarnya.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-success/20 text-success rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900">Estimasi Pickup</h4>
                                <p class="text-sm text-muted">Kurir kami akan sampai dalam waktu 30–60 menit setelah pemesanan.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-6 h-6 bg-success/20 text-success rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900">Ongkir Fleksibel</h4>
                                <p class="text-sm text-muted">Gratis biaya antar jemput untuk radius 3km dari outlet kami.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2 w-full">
                    <div class="bg-white p-2 rounded-2xl shadow-xl border border-border overflow-hidden rotate-2">
                        <div class="h-80 bg-gray-100 rounded-xl relative flex items-center justify-center border-2 border-dashed border-border group cursor-help">
                            <div class="text-center px-8 group-hover:scale-105 transition-transform">
                                <svg class="w-12 h-12 text-primary/30 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                                <span class="text-muted font-medium text-sm">Visual Peta Samarinda</span>
                                <p class="text-[10px] text-muted mt-2 uppercase tracking-widest">Melayani dengan Sepenuh Hati</p>
                            </div>
                            <div class="absolute bottom-4 right-4 bg-primary text-white text-[10px] font-bold px-3 py-1 rounded-md uppercase tracking-widest shadow-lg">Outlet HiWash</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Cara Kerja Section -->
        <div id="proses" class="py-24 px-8 bg-white overflow-hidden">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4 tracking-tight">Cara <span class="text-primary">Kerja</span> Kami</h2>
                    <p class="text-muted">Hanya 4 langkah mudah untuk mendapatkan pakaian yang bersih, rapi, dan wangi.</p>
                </div>

                <div class="relative">
                    <!-- Progress Line (Desktop) -->
                    <div class="hidden lg:block absolute top-12 left-0 w-full h-0.5 bg-border z-0"></div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12 relative z-10">
                        <!-- Step 1 -->
                        <div class="flex flex-col items-center text-center">
                            <div class="w-24 h-24 bg-secondary rounded-3xl flex items-center justify-center text-primary mb-6 shadow-xl transform rotate-3 hover:rotate-0 transition-transform duration-300">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-2">1. Booking / Order</h4>
                            <p class="text-sm text-muted leading-relaxed">Pilih layanan di aplikasi atau hubungi kami via WhatsApp.</p>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex flex-col items-center text-center">
                            <div class="w-24 h-24 bg-primary rounded-3xl flex items-center justify-center text-white mb-6 shadow-xl transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-2">2. Pickup Kurir</h4>
                            <p class="text-sm text-muted leading-relaxed">Kurir kami akan menjemput cucian langsung di depan pintu Anda.</p>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex flex-col items-center text-center">
                            <div class="w-24 h-24 bg-secondary rounded-3xl flex items-center justify-center text-primary mb-6 shadow-xl transform rotate-3 hover:rotate-0 transition-transform duration-300">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-2">3. Proses Laundry</h4>
                            <p class="text-sm text-muted leading-relaxed">Pakaian dicuci secara higienis menggunakan mesin & deterjen standar hotel.</p>
                        </div>

                        <!-- Step 4 -->
                        <div class="flex flex-col items-center text-center">
                            <div class="w-24 h-24 bg-primary rounded-3xl flex items-center justify-center text-white mb-6 shadow-xl transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                            </div>
                            <h4 class="text-xl font-bold text-gray-900 mb-2">4. Antar Bersih</h4>
                            <p class="text-sm text-muted leading-relaxed">Pakaian rapi, wangi, dan steril diantar kembali ke lokasi Anda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
