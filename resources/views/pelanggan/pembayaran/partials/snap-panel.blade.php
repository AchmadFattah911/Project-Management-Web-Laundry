{{--
    Panel bayar Midtrans Snap (PAYMENT_ENABLED=true). Halaman ini TIDAK pernah mengubah status lunas:
    setelah popup selesai, halaman hanya memverifikasi (polling) sampai notifikasi Midtrans diproses server.
    Variabel: $checkout, $snap (js_url, client_key), $verifying (bool)
--}}
@php
    $deadline = $checkout->paymentDeadline();
    $reason = $checkout->pending_reason;
@endphp

<div
    x-data="snapPay({
        payUrl: @js(route('pelanggan.pembayaran.pay', $checkout)),
        popupClosedUrl: @js(route('pelanggan.pembayaran.popup-closed', $checkout)),
        verifyUrl: @js(route('pelanggan.pembayaran.show', ['checkout' => $checkout, 'verifikasi' => 1])),
        statusUrl: @js(route('pelanggan.pembayaran.status', $checkout)),
        deadline: {{ $deadline->getTimestampMs() }},
        verifying: @js($verifying),
    })"
    class="mt-4 space-y-4"
>
    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
        <x-badge variant="warning" size="sm">Menunggu Pembayaran</x-badge>
        <p class="mt-3 text-[11px] text-amber-900">Bayar sebelum</p>
        <p class="text-2xl font-extrabold font-mono text-amber-900 tabular-nums" x-text="remainingLabel">--:--:--</p>
        <p class="text-[11px] text-amber-800">{{ $deadline->timezone(config('laundry.report_timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') }} WIB</p>
    </div>

    {{-- Kembali dari Snap: hanya menampilkan & memantau, tidak mengubah status apa pun --}}
    <div x-show="verifying" x-cloak class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-xs text-sky-900">
        <p class="font-bold flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
            Kami sedang memverifikasi pembayaran Anda.
        </p>
        <p class="mt-1">Halaman ini diperbarui otomatis setelah Midtrans mengonfirmasi. Jika Anda memilih transfer/VA, selesaikan transfer sesuai instruksi.</p>
    </div>

    @if ($reason === 'popup_ditutup')
        <p x-show="! verifying" class="text-[11px] text-slate-600">Jendela pembayaran sebelumnya ditutup sebelum selesai. Klik "Bayar Sekarang" untuk melanjutkan, tagihannya tetap sama.</p>
    @elseif ($reason === 'menunggu_transfer')
        <p class="text-[11px] text-slate-600">Tagihan sudah dibuat. Selesaikan transfer, atau klik "Bayar Sekarang" untuk melihat instruksinya lagi.</p>
    @endif

    <template x-if="! expired">
        <button
            type="button"
            x-on:click="pay()"
            :disabled="loading"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
        >
            <svg x-show="loading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span x-text="loading ? 'Menyiapkan pembayaran...' : 'Bayar Sekarang'">Bayar Sekarang</span>
        </button>
    </template>
    <template x-if="expired">
        <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800">
            Batas waktu pembayaran sudah habis. Transaksi akan ditutup otomatis; Anda bisa memesan lagi setelahnya.
        </div>
    </template>

    <p x-show="message" x-cloak x-text="message" class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800"></p>
    <p class="text-[11px] text-slate-500 text-center">Klik berkali-kali tetap memakai tagihan yang sama. Anda tidak akan ditagih dua kali.</p>
</div>

@once
    @push('scripts')
        <script src="{{ $snap['js_url'] }}" data-client-key="{{ $snap['client_key'] }}"></script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('snapPay', ({ payUrl, popupClosedUrl, verifyUrl, statusUrl, deadline, verifying }) => ({
                    loading: false,
                    message: null,
                    verifying,
                    remaining: null,
                    expired: false,
                    polls: 0,
                    init() {
                        this.tick();
                        this.timer = setInterval(() => this.tick(), 1000);
                        // Pantau status: lunas bisa datang dari webhook kapan saja (juga saat bayar di perangkat lain)
                        this.poller = setInterval(() => this.poll(), verifying ? 3000 : 10000);
                    },
                    destroy() {
                        clearInterval(this.timer);
                        clearInterval(this.poller);
                    },
                    headers() {
                        return {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        };
                    },
                    tick() {
                        this.remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                        if (this.remaining === 0) {
                            this.expired = true;
                            clearInterval(this.timer);
                        }
                    },
                    get remainingLabel() {
                        if (this.remaining === null) return '--:--:--';
                        const pad = (n) => String(n).padStart(2, '0');
                        return [Math.floor(this.remaining / 3600), Math.floor(this.remaining / 60) % 60, this.remaining % 60].map(pad).join(':');
                    },
                    async poll() {
                        if (++this.polls > 200) return clearInterval(this.poller);
                        try {
                            const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                            if (! res.ok) return;
                            const data = await res.json();
                            if (data.paid || data.status !== 'menunggu_pembayaran') {
                                window.location.href = window.location.pathname;
                            }
                        } catch (e) {
                            // Koneksi terputus sementara: coba lagi di putaran berikutnya
                        }
                    },
                    async requestToken(attempt = 0) {
                        const res = await fetch(payUrl, { method: 'POST', headers: this.headers() });
                        // Tagihan sedang dibuat request lain (klik di tab lain): tunggu lalu ambil token yang sama
                        if (res.status === 409 && attempt < 5) {
                            const wait = (Number(res.headers.get('Retry-After')) || 2) * 1000;
                            await new Promise((resolve) => setTimeout(resolve, wait));
                            return this.requestToken(attempt + 1);
                        }
                        const body = await res.json().catch(() => ({}));
                        if (! res.ok) throw new Error(body.message || 'Pembayaran belum bisa diproses. Coba lagi beberapa saat lagi.');
                        return body.data;
                    },
                    async pay() {
                        if (this.loading) return;
                        this.loading = true;
                        this.message = null;
                        try {
                            const data = await this.requestToken();
                            if (! window.snap) {
                                // snap.js gagal dimuat: pakai halaman pembayaran Midtrans (redirect)
                                window.location.href = data.redirect_url;
                                return;
                            }
                            window.snap.pay(data.snap_token, {
                                onSuccess: () => { window.location.href = verifyUrl; },
                                onPending: () => { window.location.href = verifyUrl; },
                                onError: () => {
                                    this.loading = false;
                                    this.message = 'Pembayaran gagal diproses. Silakan coba lagi atau pilih metode lain.';
                                },
                                onClose: () => {
                                    this.loading = false;
                                    this.message = 'Jendela pembayaran ditutup. Klik "Bayar Sekarang" untuk melanjutkan dengan tagihan yang sama.';
                                    fetch(popupClosedUrl, { method: 'POST', headers: this.headers() }).catch(() => {});
                                },
                            });
                        } catch (e) {
                            this.loading = false;
                            this.message = e.message;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
