@extends('layouts.dashboard')

@section('title', 'Perlu Refund')

@php
    use App\Http\Controllers\Admin\RefundController;
    use App\Models\Payment;

    $flagLabels = [
        Payment::FLAG_AMOUNT_MISMATCH   => ['Nominal tidak sesuai', 'Nominal yang dibayar berbeda dari tagihan. Transaksi tidak dilunasi.'],
        Payment::FLAG_DUPLICATE_PAID    => ['Pembayaran ganda', 'Transaksi sudah lunas lewat pembayaran lain. Pembayaran ini kelebihan.'],
        Payment::FLAG_PAID_AFTER_CLOSED => ['Dibayar setelah ditutup', 'Masuk setelah transaksi dibatalkan / kedaluwarsa / gagal.'],
    ];
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Perlu Refund</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Uang pelanggan yang masuk lewat Midtrans tetapi tidak melunasi transaksi. Lakukan refund di dashboard Midtrans
            atau transfer manual, lalu tandai di sini beserta catatannya.
        </p>
    </div>

    <div class="flex gap-2 border-b border-slate-200">
        @foreach ([RefundController::TAB_OPEN => 'Perlu Ditangani', RefundController::TAB_DONE => 'Sudah Direfund'] as $key => $label)
            <a
                href="{{ route('admin.refunds.index', ['tab' => $key]) }}"
                class="px-4 py-2 -mb-px text-xs sm:text-sm font-bold border-b-2 {{ $tab === $key ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                {{ $label }} <span class="ml-1 text-[11px] px-1.5 py-0.5 rounded-full {{ $tab === $key ? 'bg-sky-100' : 'bg-slate-100' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    @if ($payments->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200">
            <x-empty-state
                :title="$tab === RefundController::TAB_OPEN ? 'Tidak ada yang perlu direfund' : 'Belum ada refund'"
                description="Pembayaran bermasalah dari Midtrans akan muncul di sini secara otomatis."
            />
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($payments as $payment)
                @php
                    $flag = $flagLabels[$payment->review_flag] ?? [$payment->review_flag, ''];
                    $owner = $payment->checkout ?? $payment->order;
                    $customer = $payment->checkout?->user ?? $payment->order?->user;
                    $flagged = collect($payment->meta['flagged'] ?? [])->last();
                @endphp
                <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <x-badge variant="danger" size="sm">{{ $flag[0] }}</x-badge>
                            <p class="mt-2 font-mono text-sm font-bold text-slate-900 break-all">{{ $payment->reference }}</p>
                            <p class="text-[11px] text-slate-500">{{ $flag[1] }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[11px] text-slate-500">Diterima</p>
                            <p class="text-sm font-extrabold text-slate-900">{{ $flagged && $flagged['gross_amount'] !== null ? $rupiah($flagged['gross_amount']) : $rupiah($payment->amount) }}</p>
                            @if ($payment->review_flag === Payment::FLAG_AMOUNT_MISMATCH)
                                <p class="text-[11px] text-rose-600">Tagihan {{ $rupiah($payment->amount) }}</p>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px]">
                        <dt class="text-slate-500">Transaksi</dt>
                        <dd class="text-slate-800 font-semibold text-right">{{ $payment->checkout?->code ?? $payment->order?->order_code ?? '-' }} ({{ $owner?->status ?? '-' }})</dd>
                        <dt class="text-slate-500">Pelanggan</dt>
                        <dd class="text-slate-800 text-right truncate">{{ $customer?->name ?? '-' }} {{ $customer?->phone ? '· ' . $customer->phone : '' }}</dd>
                        <dt class="text-slate-500">Metode</dt>
                        <dd class="text-slate-800 text-right">{{ $payment->payment_type ?? $payment->method }}</dd>
                        <dt class="text-slate-500">Transaction ID</dt>
                        <dd class="text-slate-800 text-right font-mono break-all">{{ $flagged['transaction_id'] ?? $payment->transaction_id ?? '-' }}</dd>
                        <dt class="text-slate-500">Ditandai</dt>
                        <dd class="text-slate-800 text-right">{{ $flagged ? \Illuminate\Support\Carbon::parse($flagged['at'])->timezone(config('laundry.report_timezone', 'Asia/Jakarta'))->format('d M Y H:i') : $payment->updated_at->format('d M Y H:i') }}</dd>
                    </dl>

                    @if ($payment->refunded_at)
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-[11px] text-emerald-900">
                            <p class="font-bold">Sudah direfund {{ $payment->refunded_at->timezone(config('laundry.report_timezone', 'Asia/Jakarta'))->format('d M Y H:i') }} oleh {{ $payment->refundedBy?->name ?? '-' }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $payment->refund_note }}</p>
                        </div>
                    @else
                        <form
                            action="{{ route('admin.refunds.mark', $payment) }}"
                            method="POST"
                            class="space-y-2"
                            x-data="{ sending: false }"
                            @submit="if (sending || ! confirm('Tandai {{ $payment->reference }} sudah direfund? Pastikan dana sudah dikembalikan ke pelanggan.')) { $event.preventDefault(); return; } sending = true"
                        >
                            @csrf
                            <textarea
                                name="refund_note"
                                rows="2"
                                required
                                minlength="5"
                                maxlength="500"
                                placeholder="Catatan wajib: ID refund Midtrans / bukti transfer, tanggal, rekening tujuan"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            >{{ (int) old('payment_id') === $payment->id ? old('refund_note') : '' }}</textarea>
                            @if ($errors->has('refund_note') && (int) old('payment_id') === $payment->id)
                                <p class="text-[11px] text-rose-600">{{ $errors->first('refund_note') }}</p>
                            @endif
                            <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                            <button type="submit" :disabled="sending" class="w-full px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs disabled:opacity-60">
                                Tandai sudah direfund
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div>{{ $payments->links() }}</div>
    @endif
</div>
@endsection
