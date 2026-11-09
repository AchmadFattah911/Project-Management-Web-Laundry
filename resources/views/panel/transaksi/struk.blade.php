@use('App\Support\TransactionLabels', 'L')
@php
    $tz = \App\Support\BusinessCalendar::timezone();
    [$statusLabel] = L::status($checkout);
    $paid = $checkout->payments->firstWhere('status', \App\Models\Payment::STATUS_PAID);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $checkout->code }}</title>
    <style>
        body { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; color: #111; margin: 0; background: #f1f5f9; }
        .struk { width: 300px; margin: 16px auto; background: #fff; padding: 16px; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #999; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
        .stamp { display: inline-block; border: 2px solid #111; padding: 2px 8px; font-weight: 700; margin-top: 6px; }
        button { display: block; margin: 12px auto; padding: 8px 16px; }
        @media print { body { background: #fff; } .struk { margin: 0; } button { display: none; } }
    </style>
</head>
<body>
<div class="struk">
    <div class="c b">{{ config('laundry.store_name', 'Laundry Coin') }}</div>
    <div class="c">{{ config('laundry.address') }}</div>
    <hr>
    <table>
        <tr><td>Kode</td><td class="r b">{{ $checkout->code }}</td></tr>
        <tr><td>Waktu</td><td class="r">{{ $checkout->created_at->timezone($tz)->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Pelanggan</td><td class="r">{{ $checkout->user?->name }}</td></tr>
        <tr><td>No. HP</td><td class="r">{{ $checkout->contact_phone }}</td></tr>
        <tr><td>Kasir</td><td class="r">{{ $checkout->creator?->name ?? 'Website' }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($checkout->orders as $order)
            <tr><td colspan="2">{{ $order->serviceName() }}</td></tr>
            <tr><td>{{ $order->machine_qty }} x {{ L::rupiah($order->unit_price) }}</td><td class="r">{{ L::rupiah($order->total_price) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td class="b">TOTAL</td><td class="r b">{{ L::rupiah($checkout->total_amount) }}</td></tr>
        @if ($paid)
            <tr><td>Bayar ({{ L::method($checkout) }})</td><td class="r">{{ L::rupiah($paid->meta['cash_received'] ?? $paid->amount) }}</td></tr>
            @isset($paid->meta['change'])<tr><td>Kembali</td><td class="r">{{ L::rupiah($paid->meta['change']) }}</td></tr>@endisset
            <tr><td>Lunas</td><td class="r">{{ $paid->paid_at?->timezone($tz)->format('d/m/Y H:i') }}</td></tr>
        @endif
    </table>
    <div class="c"><span class="stamp">{{ strtoupper($statusLabel) }}</span></div>
    <hr>
    <div class="c">WA: {{ config('laundry.staff_whatsapp') }}</div>
    <div class="c">Simpan struk ini untuk mengambil cucian.</div>
</div>
<button type="button" onclick="window.print()">Cetak</button>
</body>
</html>
