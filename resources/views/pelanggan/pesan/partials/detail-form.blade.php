@php
    // Token unik per form: klik "Buat Pesanan" dua kali tetap menghasilkan 1 transaksi (dicek di server).
    $submissionToken = old('submission_token', (string) \Illuminate\Support\Str::uuid());
    $hasHours = collect($hours)->reject(fn ($v) => str_starts_with((string) $v, '[ISI'))->isNotEmpty();
@endphp

<form
    action="{{ route('pelanggan.checkout.store') }}"
    method="POST"
    x-data="{ submitting: false }"
    x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
    class="rounded-2xl border border-slate-200 bg-white shadow-xs"
>
    @csrf
    <input type="hidden" name="submission_token" value="{{ $submissionToken }}" />

    <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-slate-100 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-extrabold text-slate-900">2. Detail & Konfirmasi Pesanan</h2>
            <p class="text-xs text-slate-500 mt-0.5">Lengkapi data di bawah. Isi pesanan diambil dari keranjang di sebelah kanan.</p>
        </div>
        <a href="{{ route('pelanggan.pesan.index') }}" class="shrink-0 text-xs font-bold text-sky-700 hover:text-sky-800">← Kembali pilih layanan</a>
    </div>

    <div class="p-5 sm:p-6 space-y-5">
        @foreach (['cart', 'submission_token'] as $key)
            @error($key)
                <x-alert type="error" :message="$message" :dismissible="false" />
            @enderror
        @endforeach

        <!-- Nama pemesan (dari akun) -->
        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 flex items-center gap-3">
            <span class="w-9 h-9 rounded-full bg-sky-600 text-white flex items-center justify-center text-xs font-extrabold shrink-0">{{ $user->initials() }}</span>
            <div class="min-w-0">
                <p class="text-xs text-slate-500">Dipesan atas nama</p>
                <p class="text-sm font-bold text-slate-900 truncate">{{ $user->name }} <span class="font-normal text-slate-500">· {{ $user->email }}</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- No. HP -->
            <div>
                <label for="contact_phone" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    No. HP / WhatsApp <span class="text-rose-500">*</span>
                    <x-help-tip text="Dipakai petugas untuk mengabari jika cucian sudah selesai atau ada kendala. Tidak ditampilkan ke publik." />
                </label>
                <input
                    id="contact_phone"
                    type="tel"
                    name="contact_phone"
                    value="{{ old('contact_phone', $user->phone) }}"
                    required
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="08xxxxxxxxxx"
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500 @error('contact_phone') border-rose-400 @enderror"
                />
                @error('contact_phone')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Rencana tanggal antar -->
            <div>
                <label for="drop_off_date" class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Rencana Antar ke Outlet <span class="text-rose-500">*</span>
                    <x-help-tip text="Tanggal Anda membawa cucian ke outlet. Pengerjaan dimulai setelah cucian diterima petugas." />
                </label>
                <input
                    id="drop_off_date"
                    type="date"
                    name="drop_off_date"
                    value="{{ old('drop_off_date', now()->toDateString()) }}"
                    min="{{ now()->toDateString() }}"
                    max="{{ $dropOffMax->toDateString() }}"
                    required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500 @error('drop_off_date') border-rose-400 @enderror"
                />
                @error('drop_off_date')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @else
                    <p class="mt-1 text-[11px] text-slate-500">Paling lambat {{ $dropOffMax->translatedFormat('d F Y') }}.</p>
                @enderror
            </div>
        </div>

        @if ($hasHours)
            <div class="rounded-xl bg-sky-50 border border-sky-200 px-4 py-3 text-xs text-sky-900 flex gap-3">
                <x-icon name="clock" class="w-4 h-4 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold">Jam operasional outlet</p>
                    <p>Senin–Jumat: {{ $hours['weekday'] ?? '-' }} · Sabtu–Minggu: {{ $hours['weekend'] ?? '-' }} {{ $hours['timezone'] ?? '' }}</p>
                </div>
            </div>
        @endif

        <!-- Catatan umum -->
        <div>
            <label for="notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1.5">Catatan untuk Petugas (opsional)</label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                maxlength="1000"
                placeholder="Contoh: saya antar sore hari, mohon kabari via WhatsApp jika sudah selesai."
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
            >{{ old('notes') }}</textarea>
            @error('notes')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Persetujuan aturan -->
        <div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="agree" value="1" @checked(old('agree')) required class="mt-0.5 w-5 h-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                <span class="text-xs text-slate-700 leading-relaxed">
                    Saya sudah membaca dan menyetujui <a href="{{ route('aturan') }}" target="_blank" rel="noopener" class="font-bold text-sky-700 underline">Aturan Penggunaan</a>,
                    termasuk batas muatan per mesin, dan akan mengantar cucian ke outlet sesuai tanggal di atas.
                </span>
            </label>
            @error('agree')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="sticky bottom-0 lg:static z-20 rounded-b-2xl border-t border-slate-200 bg-white/95 backdrop-blur px-5 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs">
            <p class="text-slate-500">Total yang akan dibayar</p>
            <p class="text-lg font-extrabold text-sky-700">Rp {{ number_format($cart['total'], 0, ',', '.') }}</p>
        </div>
        <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
        >
            <span x-text="submitting ? 'Membuat pesanan...' : 'Buat Pesanan & Lanjut Bayar'">Buat Pesanan & Lanjut Bayar</span>
            <x-icon name="arrow-right" class="w-4 h-4" />
        </button>
    </div>
</form>
