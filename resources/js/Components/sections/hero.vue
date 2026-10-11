<script setup>
// Pure section component
</script>

<template>
  <div id="hero-section" class="min-h-screen lg:h-screen flex flex-col font-sans text-text bg-bg overflow-visible lg:overflow-hidden">
    <!-- Main Hero Body -->
    <div class="relative flex-1 bg-primary text-white overflow-hidden flex items-center pt-24 lg:pt-20 pb-20 lg:pb-0">
      <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-10 left-10 w-12 h-12 bg-surface rounded-full opacity-50"></div>
        <div class="absolute top-40 right-1/4 w-8 h-8 bg-surface rounded-full opacity-50"></div>
        <div class="absolute bottom-20 left-1/3 w-16 h-16 bg-surface rounded-full opacity-30"></div>
      </div>

      <!-- Compact spacing -->
      <div class="relative z-10 flex flex-col lg:flex-row items-center max-w-screen-2xl mx-auto px-6 py-6 lg:px-8 lg:py-8 gap-8 w-full">
        <div class="lg:w-1/2">
          <h1 class="text-3xl lg:text-6xl font-bold leading-tight mb-4 tracking-tighter">
            Laundry Beres,<br/>
            <span class="text-secondary text-shadow">Hidup Jadi Ringan</span>
          </h1>
          <p class="text-base lg:text-lg mb-6 lg:mb-8 max-w-md opacity-90 leading-relaxed">
            HiWash Laundry siap membantu Anda dengan layanan cepat, bersih, wangi, dan bisa dilacak real-time secara transparan.
          </p>
          <div class="flex flex-wrap items-center gap-4">
            <button class="flex items-center gap-2 px-6 lg:px-8 py-3 lg:py-4 bg-secondary text-primary font-bold rounded-xl hover:brightness-110 active:scale-95 transition-all shadow-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
              Order Sekarang
            </button>
            <button class="flex items-center gap-2 px-6 lg:px-8 py-3 lg:py-4 border-2 border-white/30 font-bold rounded-xl hover:bg-white/10 hover:border-white transition-all">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              Lacak Pesanan
            </button>
          </div>
        </div>

        <div class="hidden lg:w-1/2 relative lg:flex justify-center mt-8 lg:-mt-16">
          <div class="w-full max-w-lg h-64 lg:h-80 bg-white/10 rounded-3xl flex items-center justify-center border-4 border-white/20 backdrop-blur-xl shadow-2xl relative overflow-hidden group">
             <div class="absolute inset-0 bg-linear-to-tr from-primary/20 to-transparent group-hover:scale-110 transition-transform duration-700"></div>
             <span class="text-white font-bold text-xl drop-shadow-lg z-10">HiWash Experience</span>
          </div>

          <!-- Feature Badges -->
          <div class="absolute -top-4 left-0 bg-white p-3 rounded-2xl shadow-2xl border border-secondary/20 flex items-center gap-3 transform -rotate-2 hover:rotate-0 transition-all duration-300">
             <div class="w-10 h-10 bg-secondary/10 rounded-full flex items-center justify-center text-primary">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
             </div>
             <div class="text-[10px] uppercase tracking-widest font-bold text-primary">Bersih Maksimal<br/><span class="text-muted lowercase font-medium">Wangi Tahan Lama</span></div>
          </div>
          <div class="absolute bottom-8 -right-4 bg-white p-3 rounded-2xl shadow-2xl border border-primary/20 flex items-center gap-3 transform rotate-2 hover:rotate-0 transition-all duration-300">
             <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center text-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
             </div>
             <div class="text-[10px] uppercase tracking-widest font-bold text-primary">Pickup & Delivery<br/><span class="text-muted lowercase font-medium">Cepat & Aman</span></div>
          </div>
        </div>
      </div>

      <!-- Waves -->
      <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none z-0">
        <svg class="absolute bottom-0 w-full h-16 lg:h-32" preserveAspectRatio="none" viewBox="0 0 1440 320" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path class="fill-secondary" d="M0,128L80,144C160,160,320,192,480,197.3C640,203,800,181,960,154.7C1120,128,1280,96,1360,80L1440,64L1440,320L1360,320C1280,320,1120,320,960,320C800,320,640,320,480,320C320,320,160,320,80,320L0,320Z"></path>
        </svg>
        <svg class="relative w-full h-12 lg:h-20" preserveAspectRatio="none" viewBox="0 0 1440 320" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path class="fill-bg" d="M0,64L80,90.7C160,117,320,171,480,186.7C640,203,800,181,960,154.7C1120,128,1280,96,1360,80L1440,64L1440,320L1360,320C1280,320,1120,320,960,320C800,320,640,320,480,320C320,320,160,320,80,320L0,320Z"></path>
        </svg>
      </div>
    </div>

    <!-- Quick Features -->
    <div class="relative z-20 max-w-7xl mx-auto px-6 lg:px-8 -mt-10 mb-12 lg:mb-10">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-2xl shadow-xl p-4 lg:p-6 flex flex-col items-center text-center border border-border hover:-translate-y-1 transition-all">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-primary/5 text-primary rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"></path><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7h-3v7h.05a2.5 2.5 0 004.9 0H17a1 1 0 001-1V9l-2-2h-2z"></path></svg>
            </div>
            <h3 class="font-bold text-xs lg:text-sm text-gray-900 mb-1">Layanan Antar Jemput</h3>
            <p class="text-[10px] text-muted hidden sm:block">Kami jemput dan antar langsung ke lokasi Anda.</p>
          </div>
          <div class="bg-white rounded-2xl shadow-xl p-4 lg:p-6 flex flex-col items-center text-center border border-border hover:-translate-y-1 transition-all">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="font-bold text-xs lg:text-sm text-gray-900 mb-1">Cepat & Tepat</h3>
            <p class="text-[10px] text-muted hidden sm:block">Pengerjaan kilat dengan standar kualitas tertinggi.</p>
          </div>
          <div class="bg-white rounded-2xl shadow-xl p-4 lg:p-6 flex flex-col items-center text-center border border-border hover:-translate-y-1 transition-all">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <h3 class="font-bold text-xs lg:text-sm text-gray-900 mb-1">Aman & Terpercaya</h3>
            <p class="text-[10px] text-muted hidden sm:block">Barang Anda kami jaga dengan proteksi ekstra.</p>
          </div>
          <div class="bg-white rounded-2xl shadow-xl p-4 lg:p-6 flex flex-col items-center text-center border border-border hover:-translate-y-1 transition-all">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </div>
            <h3 class="font-bold text-xs lg:text-sm text-gray-900 mb-1">Update Real-time</h3>
            <p class="text-[10px] text-muted hidden sm:block">Pantau status cucian secara langsung via WhatsApp.</p>
          </div>
      </div>
    </div>
  </div>
</template>
