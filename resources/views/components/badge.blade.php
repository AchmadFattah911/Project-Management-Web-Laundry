@props([
    'variant' => null,
    'status' => null,   // order status: pending, diproses, siap_diambil, selesai
    'payment' => null,  // payment status: pending, paid
    'size' => 'md',     // sm, md
    'dot' => true,
])

@php
    // Lookup status pesanan laundry koin
    $orderStatusLookup = [
        'pending' => [
            'class' => 'bg-amber-50 text-amber-800 border-amber-200',
            'dot'   => 'bg-amber-500',
            'label' => 'Menunggu',
        ],
        'diproses' => [
            'class' => 'bg-sky-50 text-sky-700 border-sky-200',
            'dot'   => 'bg-sky-500',
            'label' => 'Diproses',
        ],
        'siap_diambil' => [
            'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'dot'   => 'bg-emerald-500',
            'label' => 'Siap Diambil',
        ],
        'selesai' => [
            'class' => 'bg-slate-100 text-slate-700 border-slate-300',
            'dot'   => 'bg-slate-500',
            'label' => 'Selesai',
        ],
    ];

    // Lookup status pembayaran
    $paymentStatusLookup = [
        'pending' => [
            'class' => 'bg-amber-50 text-amber-800 border-amber-200',
            'dot'   => 'bg-amber-500',
            'label' => 'Belum Lunas',
        ],
        'paid' => [
            'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'dot'   => 'bg-emerald-500',
            'label' => 'Lunas',
        ],
        'failed' => [
            'class' => 'bg-rose-50 text-rose-700 border-rose-200',
            'dot'   => 'bg-rose-500',
            'label' => 'Gagal',
        ],
        'expired' => [
            'class' => 'bg-slate-100 text-slate-600 border-slate-300',
            'dot'   => 'bg-slate-400',
            'label' => 'Kadaluarsa',
        ],
    ];

    // Lookup varian manual umum
    $manualVariantLookup = [
        'slate'   => ['class' => 'bg-slate-100 text-slate-700 border-slate-200',     'dot' => 'bg-slate-400'],
        'primary' => ['class' => 'bg-sky-50 text-sky-700 border-sky-200',           'dot' => 'bg-sky-500'],
        'success' => ['class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
        'warning' => ['class' => 'bg-amber-50 text-amber-800 border-amber-200',     'dot' => 'bg-amber-500'],
        'danger'  => ['class' => 'bg-rose-50 text-rose-700 border-rose-200',         'dot' => 'bg-rose-500'],
        'coin'    => ['class' => 'bg-amber-100 text-amber-900 border-amber-300',     'dot' => 'bg-amber-600'],
    ];

    $sizes = [
        'sm' => 'text-[11px] px-2 py-0.5 font-medium',
        'md' => 'text-xs px-2.5 py-1 font-semibold',
    ];

    $resolvedLabel = null;
    $styleData = $manualVariantLookup['slate'];

    if ($status && isset($orderStatusLookup[$status])) {
        $styleData = $orderStatusLookup[$status];
        $resolvedLabel = $orderStatusLookup[$status]['label'];
    } elseif ($payment && isset($paymentStatusLookup[$payment])) {
        $styleData = $paymentStatusLookup[$payment];
        $resolvedLabel = $paymentStatusLookup[$payment]['label'];
    } elseif ($variant && isset($manualVariantLookup[$variant])) {
        $styleData = $manualVariantLookup[$variant];
    }

    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $colorClass = $styleData['class'];
    $dotColor = $styleData['dot'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border {$sizeClass} {$colorClass}"]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
    @endif
    {{ $slot->isEmpty() ? $resolvedLabel : $slot }}
</span>
