<script setup>
// Anda bisa menambahkan logika form handling di sini jika diperlukan
</script>

<template>
  <div id="kontak" class="py-24 px-8 bg-bg border-t border-border">
    <div class="max-w-5xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">

        <div>
          <h2 class="text-3xl font-bold text-text mb-6 tracking-tight">
            Hubungi <span class="text-primary">Kami</span>
          </h2>
          <p class="text-muted mb-10 leading-relaxed max-w-md">
            Punya pertanyaan khusus atau ingin berlangganan layanan korporat? Tim kami siap membantu Anda dengan sepenuh hati.
          </p>

          <div class="space-y-8">
            <div class="flex items-start gap-4 group">
              <div class="p-3 rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-text">WhatsApp Support</h4>
                <p class="text-muted text-sm">+62 812-3456-7890</p>
              </div>
            </div>

            <div class="flex items-start gap-4 group">
              <div class="p-3 rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-text">Email Kami</h4>
                <p class="text-muted text-sm">halo@laundrykita.com</p>
              </div>
            </div>

            <div class="flex items-start gap-4 group">
              <div class="p-3 rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-text">Lokasi Workshop</h4>
                <p class="text-muted text-sm">Jl. Kebersihan No. 123, Balikpapan</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-container p-8 rounded-3xl border border-border shadow-sm">
          <form action="#" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-text mb-2">Nama Lengkap</label>
                <input type="text" placeholder="Fahri Noor"
                  class="w-full px-4 py-3 bg-bg rounded-xl border border-border focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-muted/50">
              </div>
              <div>
                <label class="block text-sm font-medium text-text mb-2">Nomor WhatsApp</label>
                <input type="tel" placeholder="0812..."
                  class="w-full px-4 py-3 bg-bg rounded-xl border border-border focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-muted/50">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-2">Pesan Anda</label>
              <textarea rows="4" placeholder="Tuliskan detail pertanyaan Anda di sini..."
                class="w-full px-4 py-3 bg-bg rounded-xl border border-border focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-muted/50"></textarea>
            </div>
            <button type="submit"
              class="w-full bg-primary text-white font-semibold py-4 rounded-xl hover:bg-primary-hover shadow-lg shadow-primary/20 transition-all active:scale-[0.98]">
              Kirim Pesan Sekarang
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</template>
