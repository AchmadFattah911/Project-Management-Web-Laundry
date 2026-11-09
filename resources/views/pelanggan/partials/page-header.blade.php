{{-- Top bar halaman pelanggan bergaya aplikasi mobile. Variabel: $title, $back (URL tombol kembali) --}}
<header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 py-3 flex items-center gap-3 print:hidden">
    <a href="{{ $back }}" class="p-1.5 -ml-1.5 rounded-full text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Kembali">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
    </a>
    <h1 class="text-sm font-extrabold text-slate-900 truncate">{{ $title }}</h1>
</header>
