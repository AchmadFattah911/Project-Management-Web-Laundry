<script setup>
// Pure section component for About Us
</script>

<template>
  <section id="tentang" class="py-24 px-8 bg-white overflow-hidden">
    <div class="max-w-7xl mx-auto">
      <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
        <!-- Image Area -->
        <div class="lg:w-1/2 relative">
          <div class="relative z-10 rounded-4xl overflow-hidden shadow-2xl transform lg:-rotate-3 hover:rotate-0 transition-transform duration-500">
            <div class="aspect-4/5 bg-gray-100 flex items-center justify-center border-4 border-white">
               <!-- Visual Placeholder for a clean laundry facility -->
               <div class="text-center px-10">
                  <svg class="w-20 h-20 text-primary/20 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                  <p class="text-muted font-medium italic">"Kebersihan adalah prioritas kami sejak hari pertama."</p>
               </div>
            </div>
          </div>
          <!-- Decorative elements -->
          <div class="absolute -top-10 -left-10 w-40 h-40 bg-secondary/20 rounded-full blur-3xl opacity-60"></div>
          <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-primary/10 rounded-full blur-3xl opacity-60"></div>
          
          <!-- Experience Badge -->
          <div class="absolute -bottom-6 -left-6 bg-primary p-6 rounded-2xl shadow-2xl z-20 hidden md:block">
            <div class="text-white">
              <span class="block text-4xl font-extrabold mb-1">5+</span>
              <span class="text-xs uppercase tracking-widest font-bold opacity-80">Tahun<br/>Melayani</span>
            </div>
          </div>
        </div>

        <!-- Text Area -->
        <div class="lg:w-1/2">
          <span class="text-primary font-bold tracking-widest uppercase text-sm mb-4 block">Tentang HiWash</span>
          <h2 class="text-4xl lg:text-5xl font-extrabold text-gray-900 mb-8 leading-tight tracking-tighter">
            Dedikasi Untuk <br/>
            <span class="text-primary">Kebersihan Sempurna</span>
          </h2>
          
          <div class="space-y-6 text-lg text-muted leading-relaxed">
            <p>
              Berawal dari sebuah outlet kecil di sudut Kota Samarinda, **HiWash Laundry** lahir dengan misi sederhana: memberikan kebebasan waktu bagi Anda dengan menangani cucian Anda secara profesional.
            </p>
            <p>
              Kami percaya bahwa pakaian bukan sekadar kain, melainkan identitas dan kenyamanan diri. Itulah mengapa setiap helai pakaian yang Anda percayakan kepada kami diproses dengan standar sterilisasi tinggi, menggunakan air berkualitas, dan deterjen ramah lingkungan yang menjaga serat kain tetap awet.
            </p>
            <p>
              Hingga saat ini, kami telah melayani ribuan keluarga dan profesional di Samarinda, terus berinovasi dengan layanan antar-jemput cepat dan sistem pelacakan pesanan real-time untuk menjamin ketenangan pikiran Anda.
            </p>
          </div>

          <!-- Mini Stats -->
          <div class="grid grid-cols-2 gap-8 mt-12 py-8 border-t border-border">
            <div>
              <h4 class="text-3xl font-extrabold text-gray-900 mb-1">10k+</h4>
              <p class="text-xs uppercase tracking-widest font-bold text-muted">Pelanggan Puas</p>
            </div>
            <div>
              <h4 class="text-3xl font-extrabold text-gray-900 mb-1">50k+</h4>
              <p class="text-xs uppercase tracking-widest font-bold text-muted">Kg Cucian Bersih</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>