@php
    $tutorialSteps = [
        ['icon' => 'washer', 'title' => 'Centang layanan', 'text' => 'Pilih satu atau beberapa layanan drop-off sekaligus. Kartu yang dicentang akan terbuka.'],
        ['icon' => 'package', 'title' => 'Atur jenis & jumlah', 'text' => 'Pilih jenis cucian dan jumlah mesin (bantuan hitung dari perkiraan berat bersifat opsional), lalu klik "Tambah ke Keranjang".'],
        ['icon' => 'cart', 'title' => 'Cek keranjang', 'text' => 'Ubah jumlah atau hapus layanan di keranjang. Klik "Lanjut" untuk mengisi No. HP & tanggal antar.'],
        ['icon' => 'coin', 'title' => 'Bayar & antar', 'text' => 'Bayar online (Transfer/QRIS), lalu antar cucian ke outlet sesuai tanggal dan tunjukkan kode pesanan.'],
    ];
@endphp

{{-- Terbuka otomatis pada kunjungan pertama; status buka/tutup diingat di browser ini. --}}
<section
    x-data="{
        open: true,
        init() {
            try { this.open = localStorage.getItem('lc_tutorial_pesan') !== 'tutup'; } catch (e) {}
        },
        toggle() {
            this.open = ! this.open;
            try { localStorage.setItem('lc_tutorial_pesan', this.open ? 'buka' : 'tutup'); } catch (e) {}
        },
    }"
    class="rounded-2xl border border-sky-200 bg-sky-50/60"
    aria-labelledby="tutorial-pesan-title"
>
    <button
        type="button"
        x-on:click="toggle()"
        class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left"
        :aria-expanded="open.toString()"
        aria-controls="tutorial-pesan-body"
    >
        <span class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-xl bg-sky-600 text-white flex items-center justify-center shrink-0">
                <x-icon name="info" class="w-5 h-5" />
            </span>
            <span>
                <span id="tutorial-pesan-title" class="block text-sm font-extrabold text-slate-900">Cara Memesan di Halaman Ini</span>
                <span class="block text-xs text-slate-600">4 langkah singkat, tidak perlu kembali ke halaman utama.</span>
            </span>
        </span>
        <span class="text-xs font-bold text-sky-700 flex items-center gap-1 shrink-0">
            <span x-text="open ? 'Sembunyikan' : 'Lihat panduan'">Sembunyikan</span>
            <span class="transition-transform" :class="open && 'rotate-180'"><x-icon name="chevron-down" class="w-4 h-4" /></span>
        </span>
    </button>

    <div id="tutorial-pesan-body" x-show="open" x-collapse>
        <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 px-5 pb-5">
            @foreach ($tutorialSteps as $i => $t)
                <li class="relative rounded-xl bg-white border border-sky-100 p-4">
                    <span class="absolute top-3 right-3 text-[10px] font-extrabold text-sky-300">0{{ $i + 1 }}</span>
                    <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center mb-2">
                        <x-icon :name="$t['icon']" class="w-4 h-4" />
                    </span>
                    <p class="text-xs font-extrabold text-slate-900">{{ $t['title'] }}</p>
                    <p class="text-[11px] text-slate-600 leading-snug mt-1">{{ $t['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
