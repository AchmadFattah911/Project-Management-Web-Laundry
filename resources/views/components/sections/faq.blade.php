@php
    $faqs = [
        [
            'q' => 'Bagaimana cara membayar pesanan di Laundry Coin?',
            'a' => 'Laundry Coin tidak menggunakan koin atau token. Untuk pesanan online, pembayaran dilakukan secara cashless melalui Transfer Bank atau E-Wallet / QRIS. Pilih metode saat membuat pesanan, bayar sesuai total tagihan, lalu petugas akan memverifikasi dan status pesanan Anda berubah menjadi Lunas. Untuk Self Laundry langsung di outlet, pembayaran dilakukan di kasir.'
        ],
        [
            'q' => 'Apakah deterjen dan pelembut pakaian disediakan gratis?',
            'a' => 'Ya, betul! Mesin cuci kami telah terhubung dengan sistem injeksi deterjen cair premium dan softener otomatis yang telah terukur secara presisi. Anda tidak perlu repot membawa atau menuangkan sabun sendiri.'
        ],
        [
            'q' => 'Berapa lama waktu yang dibutuhkan untuk mencuci dan mengeringkan pakaian?',
            'a' => 'Siklus cuci cepat berlangsung sekitar 35 menit, dan siklus pengeringan (dryer) berlangsung 40 menit. Jadi hanya dalam waktu kurang lebih 75 menit (1 jam 15 menit), pakaian Anda sudah bersih, steril bebas tungau, wangi, dan 100% kering siap pakai.'
        ],
        [
            'q' => 'Apakah saya boleh meninggalkan cucian saat mesin sedang berputar?',
            'a' => 'Boleh sekali! Pintu mesin cuci terkunci otomatis demi keamanan selama siklus berjalan. Anda bisa menunggu di waiting lounge ber-AC kami atau bepergian ke toko sekitar. Namun mohon kembali tepat waktu saat timer selesai agar tidak menghambat pengguna lain.'
        ],
        [
            'q' => 'Apa perbedaan antara layanan Self Laundry dan Drop-Off?',
            'a' => 'Self Laundry adalah layanan cuci mandiri langsung di outlet (tidak bisa dipesan online) di mana Anda sendiri yang memasukkan pakaian dan memindahkan ke pengering dengan harga hemat per siklus. Sedangkan layanan Drop-Off adalah layanan terima beres, di mana staf kami yang mencuci, mengeringkan, dan melipat rapi sesuai paket yang dipilih. Layanan Drop-Off bisa dipesan online.'
        ],
        [
            'q' => 'Apakah Laundry Coin menyediakan layanan Antar-Jemput (Pickup & Delivery)?',
            'a' => 'Saat ini Laundry Coin belum menyediakan layanan antar-jemput. Anda dapat memesan layanan Drop-Off secara online, lalu membawa dan mengambil pakaian langsung di outlet. Status pesanan bisa dipantau melalui akun Anda atau menu Lacak Pesanan.'
        ],
    ];
@endphp

<section id="faq" class="py-16 lg:py-24 bg-slate-50 border-b border-slate-200/60">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-wider border border-sky-200 mb-3">
                Pertanyaan yang Sering Diajukan
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                Ada Pertanyaan Seputar Laundry Coin?
            </h2>
            <p class="text-base text-slate-600 mt-3 leading-relaxed">
                Temukan jawaban cepat mengenai pembayaran, durasi mesin, fasilitas, dan cara pemesanan.
            </p>
        </div>

        <div x-data="{ active: null }" class="space-y-3.5">
            @foreach ($faqs as $index => $item)
                <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden shadow-xs transition-colors">
                    <button
                        type="button"
                        x-on:click="active = (active === {{ $index }} ? null : {{ $index }})"
                        class="w-full px-6 py-4.5 text-left flex items-center justify-between gap-4 font-semibold text-slate-900 hover:text-sky-600 transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 rounded-2xl"
                    >
                        <span class="text-sm sm:text-base">{{ $item['q'] }}</span>
                        <div
                            class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shrink-0 transition-transform duration-200"
                            :class="active === {{ $index }} ? 'rotate-180 bg-sky-100 text-sky-700' : ''"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>

                    <div
                        x-show="active === {{ $index }}"
                        x-collapse
                        class="border-t border-slate-100 px-6 py-4 bg-slate-50/50"
                        style="display: none;"
                    >
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            {{ $item['a'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
