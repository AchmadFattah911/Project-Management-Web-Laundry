@extends('layouts.dashboard')

@section('title', 'Transaksi ' . $checkout->code)

@section('content')
@use('App\Support\TransactionLabels', 'L')
@use('App\Models\Order', 'Order')
@use('App\Models\VoidRequest', 'VoidRequest')
@php
    $tz = \App\Support\BusinessCalendar::timezone();
    [$statusLabel, $statusVariant] = L::status($checkout);
    $voided = $checkout->isVoided();
    $pendingVoid = $checkout->voidRequests->firstWhere('status', VoidRequest::STATUS_PENDING);
    $editable = $checkout->isOutlet() && $checkout->isAwaitingPayment() && ! $voided;
    $workable = ! $voided && ($checkout->isPaid() || $checkout->allowsWorkBeforePayment());
    $paid = $checkout->payments->firstWhere('status', \App\Models\Payment::STATUS_PAID);
@endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('panel.transaksi.index', ['tab' => $checkout->isOutlet() ? 'outlet' : 'aplikasi']) }}" class="text-xs font-bold text-sky-600 hover:text-sky-700">&larr; Transaksi {{ $checkout->isOutlet() ? 'Outlet' : 'Aplikasi' }}</a>
    </div>

    <x-panel.header :title="$checkout->code" :subtitle="($checkout->isOutlet() ? 'Transaksi outlet' : 'Transaksi aplikasi') . ' · ' . $checkout->created_at->timezone($tz)->format('d/m/Y H:i') . ' WIB'">
        <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
        <a href="{{ route('panel.transaksi.receipt', $checkout) }}" target="_blank" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Lihat / Cetak Struk</a>
        @if ($checkout->isOutlet() && ! $voided && ! $pendingVoid)
            @can('transaksi.void.request')
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'ajukan-void')" class="px-4 py-2.5 rounded-xl border border-rose-200 bg-white text-xs font-bold text-rose-600 hover:bg-rose-50">Ajukan Void</button>
            @endcan
        @endif
    </x-panel.header>

    @if ($pendingVoid)
        <x-alert type="warning" :dismissible="false" :message="'Void diajukan oleh ' . ($pendingVoid->requester?->name ?? '-') . ': ' . $pendingVoid->reason . ' (menunggu persetujuan admin)'" />
    @endif

    <div class="grid gap-4 sm:grid-cols-4">
        <x-stat-card title="Total" :value="L::rupiah($checkout->total_amount)" color="sky" />
        <x-stat-card title="Pelanggan" :value="$checkout->user?->name ?? '-'" :hint="$checkout->contact_phone" color="violet" />
        <x-stat-card title="Metode" :value="L::method($checkout)" :hint="$checkout->isOutlet() ? ($checkout->payment_timing === 'on_pickup' ? 'Bayar saat ambil' : 'Bayar sekarang') : 'Pembayaran online'" color="amber" />
        <x-stat-card title="Petugas" :value="$checkout->creator?->name ?? 'Website'" :hint="$checkout->shift_id ? 'Shift #' . $checkout->shift_id : null" color="emerald" />
    </div>

    <x-card title="Item & Status Pengerjaan" padding="p-0">
        <x-table :headers="['Pesanan', 'Layanan', 'Mesin', 'Subtotal', 'Status', '']">
            @foreach ($checkout->orders as $order)
                @php
                    $next = collect(Order::STATUSES)->skipUntil(fn ($s) => $s === $order->status)->skip(1)->first();
                    $nextBlocked = $next === Order::STATUS_SELESAI && ! $checkout->isPaid();
                @endphp
                <tr data-order="{{ $order->order_code }}">
                    <td class="px-5 py-3 font-mono text-xs">{{ $order->order_code }}</td>
                    <td class="px-5 py-3 text-xs">{{ $order->serviceName() }}@if ($order->notes)<br><span class="text-slate-500">{{ $order->notes }}</span>@endif</td>
                    <td class="px-5 py-3 text-xs">{{ $order->machine_qty }} &times; {{ L::rupiah($order->unit_price) }}</td>
                    <td class="px-5 py-3 text-xs font-bold">{{ L::rupiah($order->total_price) }}</td>
                    <td class="px-5 py-3"><x-badge :status="$order->status" size="sm" /></td>
                    <td class="px-5 py-3 text-right">
                        @can('transaksi.status.update')
                            @if ($workable && $next)
                                @if ($nextBlocked)
                                    <span class="text-[11px] text-amber-700">Lunasi dulu</span>
                                @else
                                    <x-panel.form :action="route('panel.transaksi.order-status', $order)" method="PUT" :token="false" :confirm="'Ubah status ' . $order->order_code . ' menjadi ' . Order::STATUS_LABELS[$next] . '?'">
                                        <input type="hidden" name="status" value="{{ $next }}" />
                                        <x-panel.submit class="px-3! py-1.5!">{{ Order::STATUS_LABELS[$next] }}</x-panel.submit>
                                    </x-panel.form>
                                @endif
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($editable)
            @can('transaksi.outlet.settle')
                <x-card title="Pelunasan (bayar saat ambil)">
                    <x-panel.form :action="route('panel.transaksi.settle', $checkout)" :confirm="'Lunasi transaksi ' . $checkout->code . ' sebesar ' . L::rupiah($checkout->total_amount) . '?'">
                      <div x-data="{ method: 'cash' }" class="space-y-3">
                        <label class="block text-xs"><span class="font-bold text-slate-700">Metode</span>
                            <select name="payment_method" x-model="method" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                @foreach (config('finance.outlet_methods') as $key => $m)
                                    <option value="{{ $key }}">{{ $m['label'] }}</option>
                                @endforeach
                            </select></label>
                        <label class="block text-xs" x-show="method === 'cash'"><span class="font-bold text-slate-700">Uang diterima</span>
                            <input type="number" name="cash_received" min="1" :disabled="method !== 'cash'" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                        <label class="block text-xs" x-show="method !== 'cash'" x-cloak><span class="font-bold text-slate-700">Nomor bukti</span>
                            <input type="text" name="payment_reference" maxlength="60" :disabled="method === 'cash'" class="mt-1 w-full rounded-xl border-slate-200 text-sm uppercase" /></label>
                        <x-panel.submit variant="success">Lunasi {{ L::rupiah($checkout->total_amount) }}</x-panel.submit>
                      </div>
                    </x-panel.form>
                </x-card>
            @endcan
            @can('transaksi.outlet.update')
                <x-card title="Ubah Transaksi (sebelum lunas)">
                    <x-panel.form :action="route('panel.transaksi.update', $checkout)" method="PUT" :token="false" class="space-y-3">
                        @foreach ($checkout->orders as $order)
                            <label class="flex items-center justify-between gap-3 text-xs"><span>{{ $order->serviceName() }} ({{ $order->order_code }})</span>
                                <input type="number" name="qty[{{ $order->id }}]" value="{{ $order->machine_qty }}" min="1" max="{{ config('laundry.max_machine_qty', 20) }}" class="w-20 rounded-xl border-slate-200 text-sm" /></label>
                        @endforeach
                        <label class="block text-xs"><span class="font-bold text-slate-700">Catatan</span>
                            <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ $checkout->notes }}</textarea></label>
                        <x-panel.submit variant="secondary">Simpan Perubahan</x-panel.submit>
                    </x-panel.form>
                </x-card>
            @endcan
        @endif

        <x-card title="Pembayaran">
            @if ($paid)
                <p class="text-xs text-slate-700">Lunas {{ $paid->paid_at?->timezone($tz)->format('d/m/Y H:i') }} WIB · {{ L::method($checkout) }} · {{ L::rupiah($paid->amount) }}</p>
                @if ($paid->reference)<p class="text-xs text-slate-500 mt-1">Bukti: <span class="font-mono">{{ $paid->reference }}</span></p>@endif
                @if (isset($paid->meta['cash_received']))<p class="text-xs text-slate-500 mt-1">Diterima {{ L::rupiah($paid->meta['cash_received']) }}, kembali {{ L::rupiah($paid->meta['change'] ?? 0) }}</p>@endif
            @else
                <p class="text-xs text-slate-500">Belum ada pembayaran lunas.</p>
            @endif
        </x-card>

        @if ($checkout->voidRequests->isNotEmpty())
            <x-card title="Riwayat Void">
                <ul class="space-y-2 text-xs">
                    @foreach ($checkout->voidRequests as $void)
                        <li class="border-b border-slate-100 pb-2">
                            <span class="font-bold">{{ VoidRequest::STATUS_LABELS[$void->status] }}</span> · diajukan {{ $void->requester?->name }}: {{ $void->reason }}
                            @if ($void->decided_at)<br><span class="text-slate-500">Diputuskan {{ $void->decider?->name }} {{ $void->decided_at->timezone($tz)->format('d/m/Y H:i') }}{{ $void->decision_note ? ': ' . $void->decision_note : '' }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    @include('pelanggan.partials.timeline', ['timeline' => $timeline])
</div>

@if ($checkout->isOutlet() && ! $voided && ! $pendingVoid && auth()->user()->can('transaksi.void.request'))
    <x-modal name="ajukan-void" title="Ajukan Void" maxWidth="md">
        <x-panel.form :action="route('panel.transaksi.void', $checkout)" class="space-y-4" confirm="Kirim pengajuan void ke admin?">
            <input type="hidden" name="_modal" value="ajukan-void" />
            <p class="text-xs text-slate-600">Transaksi asli tidak dihapus. Jika admin menyetujui, nominalnya dibalik di laporan keuangan pada tanggal persetujuan.</p>
            <x-panel.field label="Alasan void" name="reason" required minlength="10" hint="Minimal 10 karakter." />
            <div class="flex justify-end"><x-panel.submit variant="danger">Ajukan Void</x-panel.submit></div>
        </x-panel.form>
    </x-modal>
@endif
@endsection
