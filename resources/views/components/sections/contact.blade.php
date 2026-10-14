<section id="kontak" class="py-16 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            <!-- Left Info Column -->
            <div class="lg:col-span-5 space-y-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-wider border border-sky-200">
                    Hubungi Laundry Coin
                </span>

                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                    Butuh Informasi atau Penjemputan Cucian?
                </h2>

                <p class="text-base text-slate-600 leading-relaxed">
                    Staf kami siap melayani pertanyaan seputar ketersediaan mesin cuci mandiri, paket langganan kiloan, atau penjemputan ke alamat Anda.
                </p>

                <!-- Direct WhatsApp CTA Box -->
                <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center font-bold">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-base leading-tight">Fast Response WhatsApp</h3>
                            <p class="text-xs text-emerald-100">Aktif jam operasional {{ config('laundry.operating_hours.weekday', '07:00 - 22:00') }} {{ config('laundry.operating_hours.timezone', 'WIB') }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-emerald-50 leading-relaxed mb-4">
                        Kirim pesan instan untuk konsultasi kapasitas cucian, ketersediaan mesin cuci mandiri, atau pertanyaan seputar layanan kami.
                    </p>
                    <a
                        href="https://wa.me/{{ preg_replace('/[^0-9]/', '', config('laundry.whatsapp', '')) }}?text=Halo%20{{ urlencode(config('laundry.store_name', 'Laundry Coin')) }},%20saya%20ingin%20tanya%20layanan"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-white text-emerald-800 hover:bg-emerald-50 font-bold text-xs shadow-xs transition-colors"
                    >
                        <span>Chat WhatsApp Sekarang</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            <!-- Right Contact Form Card -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-3xl p-8 border border-slate-200/90 shadow-lg">
                    <h3 class="text-xl font-bold text-slate-900 mb-2">Kirim Pesan atau Pertanyaan</h3>
                    <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                        Silakan tinggalkan pesan melalui formulir di bawah ini. Kami akan membalas via WhatsApp atau Email secepatnya.
                    </p>

                    <form action="#" method="POST" class="space-y-4" onsubmit="event.preventDefault(); alert('Terima kasih! Pesan Anda telah diterima oleh tim Laundry Coin.'); this.reset();">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="contact-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nama Lengkap <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="contact-name"
                                    type="text"
                                    required
                                    placeholder="Contoh: Budi Santoso"
                                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div>
                                <label for="contact-phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nomor WhatsApp <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="contact-phone"
                                    type="tel"
                                    required
                                    placeholder="0812xxxxxxxx"
                                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="contact-service" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                                Jenis Layanan yang Ditanyakan
                            </label>
                            <select
                                id="contact-service"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            >
                                <option value="self-laundry">Self Laundry (Cuci & Kering Mandiri di Outlet)</option>
                                <option value="dropoff">Drop-Off Kiloan / Express</option>
                                <option value="satuan">Cuci Bedcover / Sepatu / Satuan</option>
                                <option value="lainnya">Pertanyaan Lainnya</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact-message" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                                Pesan atau Detail Alamat <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="contact-message"
                                rows="4"
                                required
                                placeholder="Tuliskan pertanyaan atau alamat penjemputan Anda..."
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3 px-6 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all active:scale-[0.98]"
                        >
                            Kirim Pesan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
