{{-- Bagian pembayaran form pesanan kasir. Memakai estimatedTotal dari x-data halaman; nominal final tetap dihitung server. --}}
@php
    $formFailed = old('service_id') !== null;
@endphp

<div
    x-data="{
        method: {{ Js::from(old('payment_method', 'cash')) }},
        received: {{ Js::from(old('cash_received', '')) }},
        get paid() { return Number(this.received || 0); },
        get shortfall() { return Math.max(0, estimatedTotal - this.paid); },
        get change() { return Math.max(0, this.paid - estimatedTotal); },
    }"
    class="rounded-xl p-4 bg-slate-50 border border-slate-200 space-y-3"
>
    <input type="hidden" name="submission_token" value="{{ Str::uuid() }}" />

    @if ($formFailed && ($errors->any() || session('error')))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold space-y-1" role="alert">
            @if (session('error'))
                <p>{{ session('error') }}</p>
            @endif
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Total Tagihan</span>
        <span class="text-sm font-extrabold text-sky-700" x-text="'Rp ' + estimatedTotal.toLocaleString('id-ID')"></span>
    </div>

    <div>
        <label for="cashier-method" class="block text-[11px] font-semibold text-slate-600 mb-1">Metode Bayar</label>
        <select
            id="cashier-method"
            name="payment_method"
            x-model="method"
            required
            class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs text-slate-900 bg-white"
        >
            <option value="cash">Tunai (Cash)</option>
            <option value="transfer">Transfer Bank</option>
            <option value="e-wallet">QRIS / E-Wallet</option>
        </select>
    </div>

    <!-- Tunai: uang diterima dicatat untuk audit laci kasir -->
    <div x-show="method === 'cash'" class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <label for="cashier-received" class="block text-[11px] font-semibold text-slate-600">Uang Diterima</label>
            <button type="button" @click="received = estimatedTotal" class="text-[11px] font-bold text-sky-600 hover:text-sky-700">Uang pas</button>
        </div>
        <input
            id="cashier-received"
            type="number"
            name="cash_received"
            x-model="received"
            :required="method === 'cash'"
            :disabled="method !== 'cash'"
            :min="estimatedTotal"
            max="100000000"
            step="1"
            placeholder="Nominal yang diterima dari pelanggan"
            class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs text-slate-900 bg-white"
        />
        <p x-show="shortfall > 0" class="text-[11px] font-bold text-rose-600" x-text="'Kurang Rp ' + shortfall.toLocaleString('id-ID')"></p>
        <p x-show="shortfall === 0 && paid > 0" class="text-[11px] font-bold text-emerald-700" x-text="'Kembalian Rp ' + change.toLocaleString('id-ID')"></p>
    </div>

    <!-- Non-tunai: nomor bukti wajib agar bisa dicocokkan dengan mutasi -->
    <div x-show="method !== 'cash'">
        <label for="cashier-reference" class="block text-[11px] font-semibold text-slate-600 mb-1">Nomor Bukti Transaksi</label>
        <input
            id="cashier-reference"
            type="text"
            name="payment_reference"
            value="{{ old('payment_reference') }}"
            :required="method !== 'cash'"
            :disabled="method === 'cash'"
            maxlength="60"
            placeholder="Nomor referensi di bukti transfer / QRIS"
            class="w-full rounded-lg border border-slate-300 py-1.5 px-2.5 text-xs text-slate-900 bg-white"
        />
        <p class="mt-1 text-[11px] text-slate-500">Cek dulu uangnya sudah masuk. Satu nomor bukti hanya bisa dipakai sekali.</p>
    </div>

    <p class="pt-2 border-t border-slate-200 text-[11px] text-slate-500">
        Terima pembayaran dulu. Pesanan langsung tercatat lunas atas nama Anda dan tidak bisa diubah lagi.
    </p>
</div>
