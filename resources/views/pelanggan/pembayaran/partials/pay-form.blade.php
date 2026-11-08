{{--
    Form bayar. Token pembayaran dihitung server dari transaksi + metode: klik ganda, reload, atau tab ganda = 1 tagihan.
    Variabel: $checkout, $buttonLabel, $selected (metode terpilih, opsional), $idPrefix
--}}
<form
    action="{{ route('pelanggan.pembayaran.pay', $checkout) }}"
    method="POST"
    x-data="{ submitting: false }"
    x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
    class="space-y-4"
>
    @csrf

    <x-payment-method-picker :id-prefix="$idPrefix ?? 'pay'" :selected="$selected ?? null" />

    <button
        type="submit"
        :disabled="submitting"
        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold shadow-sm disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
    >
        <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span x-text="submitting ? 'Memproses pembayaran...' : @js($buttonLabel)">{{ $buttonLabel }}</span>
    </button>
    <p class="text-[11px] text-slate-500 text-center">Tombol terkunci setelah diklik. Pembayaran tidak akan diproses dua kali.</p>
</form>
