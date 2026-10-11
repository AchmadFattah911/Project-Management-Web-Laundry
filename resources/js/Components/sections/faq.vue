<script setup>
import { ref } from 'vue';

const active = ref(null);

const toggleFaq = (id) => {
    active.value = active.value === id ? null : id;
};
</script>

<template>
    <div id="layanan" class="font-sans text-text bg-bg overflow-x-hidden">
        <div id="faq" class="py-24 px-8 bg-white border-t border-gray-100">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4 tracking-tight">Pertanyaan <span class="text-primary">Umum</span></h2>
                    <p class="text-gray-500">Bingung? Kami punya jawabannya untuk Anda.</p>
                </div>

                <div class="w-full">
                    <!-- Item 1 -->
                    <div class="border-b border-gray-200">
                        <button @click="toggleFaq(1)" class="w-full flex justify-between items-center py-5 text-left focus:outline-none group">
                            <span class="text-lg text-gray-800 font-medium transition-colors" :class="active === 1 ? 'font-semibold text-primary' : 'group-hover:text-primary'">
                                Bagaimana layanan laundry Anda bekerja?
                            </span>
                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" :class="active === 1 ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-40 opacity-100"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="max-h-40 opacity-100"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="active === 1" class="pb-5 text-gray-600 leading-relaxed overflow-hidden">
                                Proses kami sangat mudah: 1. Pesan via web/app. 2. Kurir kami menjemput cucian Anda. 3. Cucian diproses (cuci, kering, setrika). 4. Kami kirim kembali cucian bersih Anda.
                            </div>
                        </transition>
                    </div>

                    <!-- Item 2 -->
                    <div class="border-b border-gray-200">
                        <button @click="toggleFaq(2)" class="w-full flex justify-between items-center py-5 text-left focus:outline-none group">
                            <span class="text-lg text-gray-800 font-medium transition-colors" :class="active === 2 ? 'font-semibold text-primary' : 'group-hover:text-primary'">
                                Jenis laundry apa yang Anda terima?
                            </span>
                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" :class="active === 2 ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-40 opacity-100"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="max-h-40 opacity-100"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="active === 2" class="pb-5 text-gray-600 leading-relaxed overflow-hidden">
                                Kami menerima berbagai jenis laundry, termasuk cuci kiloan untuk pakaian sehari-hari, cuci satuan (VIP) untuk pakaian formal seperti jas dan dress, serta cuci item rumah tangga seperti bed cover, sprei, dan handuk.
                            </div>
                        </transition>
                    </div>

                    <!-- Item 3 -->
                    <div class="border-b border-gray-200">
                        <button @click="toggleFaq(3)" class="w-full flex justify-between items-center py-5 text-left focus:outline-none group">
                            <span class="text-lg text-gray-800 font-medium transition-colors" :class="active === 3 ? 'font-semibold text-primary' : 'group-hover:text-primary'">
                                Bagaimana cara memberikan instruksi khusus untuk cucian saya?
                            </span>
                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" :class="active === 3 ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-40 opacity-100"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="max-h-40 opacity-100"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="active === 3" class="pb-5 text-gray-600 leading-relaxed overflow-hidden">
                                Anda bisa memberikan instruksi khusus saat memesan. Ada kolom 'Catatan' di mana Anda bisa menuliskan instruksi seperti 'jangan disetrika' atau 'periksa kantong celana'. Instruksi ini akan diteruskan ke tim workshop kami.
                            </div>
                        </transition>
                    </div>

                    <!-- Item 4 -->
                    <div class="border-b border-gray-200">
                        <button @click="toggleFaq(4)" class="w-full flex justify-between items-center py-5 text-left focus:outline-none group">
                            <span class="text-lg text-gray-800 font-medium transition-colors" :class="active === 4 ? 'font-semibold text-primary' : 'group-hover:text-primary'">
                                Bagaimana jika saya memiliki noda pada pakaian saya?
                            </span>
                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" :class="active === 4 ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-40 opacity-100"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="max-h-40 opacity-100"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="active === 4" class="pb-5 text-gray-600 leading-relaxed overflow-hidden">
                                Jangan khawatir! Kami memiliki tim khusus yang berpengalaman dalam penanganan noda. Anda bisa menandai lokasi noda dan memberikan informasi jenis noda saat penjemputan atau melalui kolom instruksi khusus saat pemesanan.
                            </div>
                        </transition>
                    </div>

                    <!-- Item 5 -->
                    <div class="border-b border-gray-200">
                        <button @click="toggleFaq(5)" class="w-full flex justify-between items-center py-5 text-left focus:outline-none group">
                            <span class="text-lg text-gray-800 font-medium transition-colors" :class="active === 5 ? 'font-semibold text-primary' : 'group-hover:text-primary'">
                                Berapa lama proses laundrynya?
                            </span>
                            <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" :class="active === 5 ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="max-h-0 opacity-0 overflow-hidden"
                            enter-to-class="max-h-40 opacity-100"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="max-h-40 opacity-100"
                            leave-to-class="max-h-0 opacity-0 overflow-hidden"
                        >
                            <div v-if="active === 5" class="pb-5 text-gray-600 leading-relaxed overflow-hidden">
                                Untuk layanan Reguler, estimasi selesai adalah 2-3 hari. Sedangkan untuk Express, kami bisa menyelesaikannya dalam hari yang sama (same-day).
                            </div>
                        </transition>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
