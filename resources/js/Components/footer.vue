<script setup>
import { Link } from '@inertiajs/vue3';

const currentYear = new Date().getFullYear();
</script>

<template>
  <footer class="bg-primary text-white pt-20 pb-10 px-8">
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-20">

        <div class="space-y-6">
          <div class="flex items-center gap-3">
            <div class="flex items-center justify-center">
              <img src="logo.png" alt="Logo" class="w-12 h-12 rounded-full">
            </div>

            <div class="flex flex-col">
              <span class="text-xl font-black tracking-tighter leading-none">HiWash</span>
              <span class="text-secondary text-sm font-bold tracking-[0.2em]">LAUNDRY   </span>
            </div>
          </div>
          <p class="text-white/80 leading-relaxed text-sm">
            Standar baru dalam perawatan pakaian. Menggabungkan teknologi modern dengan ketelitian tradisional untuk hasil yang tak tertandingi.
          </p>
          <div class="flex gap-3">
            <a href="https://www.instagram.com/hiwash.id/" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-secondary hover:text-primary transition-all duration-300">
              <i class="fab fa-instagram text-lg"></i>
            </a>
            <a href="https://www.facebook.com/people/HiWash-Laundry/61557078466426/?ref=NONE_xav_ig_profile_page_web#" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-secondary hover:text-primary transition-all duration-300">
              <i class="fab fa-facebook text-lg"></i>
            </a>
            <a href="https://api.whatsapp.com/send?phone=6285186869231" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-secondary hover:text-primary transition-all duration-300">
              <i class="fab fa-whatsapp text-lg"></i>
            </a>
          </div>
        </div>

        <div>
          <h4 class="text-secondary font-bold uppercase tracking-widest text-xs mb-8">Navigasi Utama</h4>
          <ul class="space-y-4">
            <li><Link :href="route('home')" class="text-white/70 hover:text-white hover:translate-x-1 inline-block transition-all">Beranda</Link></li>
            <li><a href="#layanan" class="text-white/70 hover:text-white hover:translate-x-1 inline-block transition-all">Layanan</a></li>
            <li><a href="#kontak" class="text-white/70 hover:text-white hover:translate-x-1 inline-block transition-all">Kontak</a></li>
            <li><a href="#faq" class="text-white/70 hover:text-white hover:translate-x-1 inline-block transition-all">FAQ</a></li>
          </ul>
        </div>

        <div>
          <h4 class="text-secondary font-bold uppercase tracking-widest text-xs mb-8">Eksplorasi Layanan</h4>
          <ul class="space-y-4">
            <li class="flex items-center gap-2 text-white/70 hover:text-white transition-colors">
              <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Cuci Kiloan Higienis
            </li>
            <li class="flex items-center gap-2 text-white/70 hover:text-white transition-colors">
              <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Premium Dry Cleaning
            </li>
            <li class="flex items-center gap-2 text-white/70 hover:text-white transition-colors">
              <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Perawatan Sepatu & Tas
            </li>
            <li class="flex items-center gap-2 text-white/70 hover:text-white transition-colors">
              <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Express Service
            </li>
          </ul>
        </div>

        <div class="bg-white/5 p-6 rounded-3xl border border-white/10 backdrop-blur-sm">
          <h4 class="text-white font-bold mb-6 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Jam Operasional
          </h4>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between border-b border-white/10 pb-2">
              <span class="text-white/60">Senin - Sabtu</span>
              <span class="font-bold text-secondary">08:00 - 20:00</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/60">Minggu</span>
              <span class="bg-secondary text-primary px-2 py-0.5 rounded text-[10px] font-black uppercase">Tutup</span>
            </div>
          </div>
          <div class="mt-6 pt-4 border-t border-white/10 text-[11px] text-white/40 italic leading-tight">
            *Layanan pickup & delivery mengikuti jam operasional workshop.
          </div>
        </div>
      </div>

      <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="text-sm text-white/50 text-center md:text-left">
          &copy; {{ currentYear }} <span class="text-white font-bold">HiWash Laundry</span>.
          Hak Cipta Dilindungi
        </div>
        <!-- <div class="flex gap-8 text-xs font-bold uppercase tracking-widest">
          <a href="#" class="text-white/40 hover:text-secondary transition-colors">Privacy Policy</a>
          <a href="#" class="text-white/40 hover:text-secondary transition-colors">Terms</a>
        </div> -->
      </div>
    </div>
  </footer>
</template>
