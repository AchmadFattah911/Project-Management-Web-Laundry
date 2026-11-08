{{-- Batalkan transaksi yang belum dibayar. Variabel: $checkout --}}
<form
    action="{{ route('pelanggan.pembayaran.cancel', $checkout) }}"
    method="POST"
    x-data="{ sending: false }"
    x-on:submit="if (sending || ! confirm('Batalkan transaksi {{ $checkout->code }}? Tagihan yang belum dibayar akan ditutup.')) { $event.preventDefault(); return; } sending = true"
    class="mt-4 border-t border-slate-100 pt-3 text-center"
>
    @csrf
    <button type="submit" :disabled="sending" class="text-xs font-bold text-rose-600 hover:text-rose-700 disabled:opacity-60">
        Batalkan Pesanan
    </button>
</form>
