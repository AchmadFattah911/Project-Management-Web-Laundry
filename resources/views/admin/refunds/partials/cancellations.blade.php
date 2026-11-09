{{-- Refund dari pembatalan transaksi online setelah bayar (App\Services\RefundService). Variabel: $cancellations, $tab --}}
@use('App\Models\Refund', 'Refund')
@use('App\Support\TransactionLabels', 'L')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<section class="mt-8 space-y-3" aria-label="Refund pembatalan pesanan">
    <h2 class="text-base font-extrabold text-slate-900">Refund Pembatalan Pesanan</h2>
    <p class="text-xs text-slate-500">Pelanggan membatalkan transaksi online yang sudah dibayar. Metode yang didukung Refund API Midtrans diproses otomatis; VA/transfer bank perlu transfer manual ke rekening pelanggan.</p>

    @if ($cancellations->isEmpty())
        <x-card padding="p-0"><x-empty-state title="Tidak ada refund pembatalan" description="Refund pembatalan yang perlu ditangani akan tampil di sini." /></x-card>
    @else
        @foreach ($cancellations as $refund)
            <x-card padding="p-4">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4" data-refund="{{ $refund->id }}">
                    <div class="text-xs space-y-1">
                        <p><span class="font-mono font-bold">{{ $refund->checkout->code }}</span> · {{ $refund->checkout->user?->name }} · <span class="font-bold">{{ L::rupiah($refund->amount) }}</span></p>
                        <p>Metode: {{ $refund->isApi() ? 'Refund API Midtrans (' . $refund->payment?->payment_type . ')' : 'Transfer manual' }} · Status: <span class="font-bold">{{ Refund::STATUS_LABELS[$refund->status] }}</span> · Percobaan: {{ $refund->attempts }}</p>
                        <p class="text-slate-500">Diajukan {{ $refund->created_at->timezone($tz)->format('d/m/Y H:i') }} WIB · refund_key <span class="font-mono">{{ $refund->refund_key }}</span></p>
                        @if ($refund->method === Refund::METHOD_MANUAL)
                            <p>Rekening: {{ $refund->bank_name }} {{ $refund->account_number }} a.n. {{ $refund->account_holder }}</p>
                        @endif
                        @if ($refund->last_error)<p class="text-rose-600">Error terakhir: {{ $refund->last_error }}</p>@endif
                        @if ($refund->completed_at)
                            <p class="text-emerald-700">Selesai {{ $refund->completed_at->timezone($tz)->format('d/m/Y H:i') }}{{ $refund->processor ? ' oleh ' . $refund->processor->name : ' (konfirmasi Midtrans)' }}{{ $refund->note ? ': ' . $refund->note : '' }}</p>
                            @if ($refund->proof_path)<a href="{{ route('admin.refunds.cancellation.proof', $refund) }}" target="_blank" class="font-bold text-sky-600">Lihat bukti transfer</a>@endif
                        @endif
                    </div>

                    @can('refund.process')
                        @if ($refund->method === Refund::METHOD_MANUAL && $refund->status === Refund::STATUS_PROCESSING)
                            <x-panel.form :action="route('admin.refunds.cancellation.complete', $refund)" :token="false" :files="true" class="space-y-2 lg:w-80" :confirm="'Tandai refund ' . $refund->checkout->code . ' sudah ditransfer?'">
                                <label class="block text-xs font-bold text-slate-700">Bukti transfer
                                    <input type="file" name="proof" required accept="image/*,application/pdf" class="mt-1 w-full text-xs" /></label>
                                <input type="text" name="note" required minlength="5" maxlength="1000" placeholder="Catatan: bank, no. referensi, tanggal" class="w-full rounded-xl border-slate-200 text-xs" />
                                <x-panel.submit variant="success" class="w-full">Tandai Refund Selesai</x-panel.submit>
                            </x-panel.form>
                        @elseif ($refund->isApi() && $refund->status === Refund::STATUS_FAILED)
                            <x-panel.form :action="route('admin.refunds.cancellation.retry', $refund)" :token="false" :confirm="'Coba ulang refund ' . $refund->checkout->code . ' ke Midtrans?'">
                                <x-panel.submit>Coba Lagi</x-panel.submit>
                            </x-panel.form>
                        @endif
                    @endcan
                </div>
            </x-card>
        @endforeach
        {{ $cancellations->links() }}
    @endif
</section>
