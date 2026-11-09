{{--
    Pembatalan setelah bayar (App\Services\OnlineCancellationService) + status refund.
    Variabel: $checkout, $cancellation (hasil eligibility)
--}}
@use('App\Models\Refund', 'Refund')
@php
    $refund = $checkout->refund;
    $rupiah = fn ($v) => 'Rp ' . number_format((int) $v, 0, ',', '.');
@endphp

@if ($refund)
    @php
        $tone = [
            'selesai'  => 'bg-emerald-50 border-emerald-200',
            'gagal'    => 'bg-amber-50 border-amber-200',
            'diproses' => 'bg-sky-50 border-sky-200',
        ][$refund->status];
    @endphp
    <section class="rounded-2xl border p-4 text-xs space-y-1 {{ $tone }} print:hidden" data-refund-status="{{ $refund->status }}">
        <p class="font-extrabold text-slate-900">Pesanan dibatalkan · Refund {{ strtolower(Refund::STATUS_LABELS[$refund->status]) }}</p>
        <p class="text-slate-700">Nominal refund {{ $rupiah($refund->amount) }} {{ $refund->isApi() ? 'dikembalikan ke metode pembayaran Anda lewat Midtrans.' : 'ditransfer admin ke rekening ' . $refund->bank_name . ' ' . $refund->maskedAccount() . '.' }}</p>
        @if ($refund->status === Refund::STATUS_FAILED)
            <p class="text-amber-800">Refund otomatis belum berhasil. Admin akan memprosesnya ulang; hubungi WhatsApp pegawai di nota bila perlu.</p>
        @endif
    </section>
@elseif ($cancellation['allowed'])
    <section
        class="bg-white rounded-2xl border border-rose-200 p-4 space-y-3 print:hidden"
        x-data="{
            deadline: {{ $cancellation['deadline']->getTimestampMs() }},
            remaining: null,
            open: {{ $errors->any() ? 'true' : 'false' }},
            init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
            tick() { this.remaining = Math.max(0, Math.floor((this.deadline - Date.now()) / 1000)); if (this.remaining === 0) clearInterval(this.timer); },
            get label() { const p = (n) => String(n).padStart(2, '0'); return this.remaining === null ? '--:--' : p(Math.floor(this.remaining / 60)) + ':' + p(this.remaining % 60); },
        }"
        data-cancel-deadline="{{ $cancellation['deadline']->toIso8601String() }}"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-extrabold text-slate-900">Berubah pikiran?</p>
                <p class="text-[11px] text-slate-600">Pesanan bisa dibatalkan sebelum cucian diterima outlet.</p>
            </div>
            <span class="font-mono text-sm font-extrabold tabular-nums" :class="remaining === 0 ? 'text-slate-400' : 'text-rose-600'" x-text="remaining === 0 ? 'Habis' : label">--:--</span>
        </div>

        <button type="button" x-show="! open && remaining !== 0" x-on:click="open = true" class="w-full py-2.5 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold">Batalkan Pesanan</button>
        <p x-show="remaining === 0" x-cloak class="text-[11px] text-slate-500">Batas waktu pembatalan sudah lewat.</p>

        <form
            x-show="open && remaining !== 0" x-cloak
            action="{{ route('pelanggan.transaksi.cancel', $checkout) }}" method="POST" class="space-y-3"
            x-data="{ sending: false }"
            x-on:submit="if (sending || ! confirm('Batalkan pesanan {{ $checkout->code }}? Dana {{ $rupiah($checkout->totalRupiah()) }} akan direfund.')) { $event.preventDefault(); return; } sending = true"
        >
            @csrf
            @if ($cancellation['method'] === Refund::METHOD_MANUAL)
                <p class="text-[11px] text-slate-600">Pembayaran Anda (transfer/VA) direfund lewat transfer oleh admin. Isi rekening tujuan:</p>
                @foreach (['bank_name' => 'Nama bank', 'account_number' => 'Nomor rekening', 'account_holder' => 'Nama pemilik rekening'] as $field => $label)
                    <label class="block text-xs"><span class="font-bold text-slate-700">{{ $label }}</span>
                        <input type="text" name="{{ $field }}" value="{{ old($field) }}" required @if ($field === 'account_number') inputmode="numeric" @endif class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                        @error($field)<span class="text-[11px] text-rose-600">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            @else
                <p class="text-[11px] text-slate-600">Dana dikembalikan otomatis ke metode pembayaran Anda lewat Midtrans.</p>
            @endif
            <label class="block text-xs"><span class="font-bold text-slate-700">Alasan (opsional)</span>
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
            <button type="submit" :disabled="sending" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold disabled:opacity-60">Ya, Batalkan &amp; Refund</button>
        </form>
    </section>
@endif
