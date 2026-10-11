@props([
    'variant' => 'slate', // slate, primary, success, warning, danger, coin
    'size' => 'md', // sm, md
    'dot' => false,
])

@php
    $sizes = [
        'sm' => 'text-[11px] px-2 py-0.5 font-medium',
        'md' => 'text-xs px-2.5 py-1 font-semibold',
    ];

    $variants = [
        'slate' => 'bg-slate-100 text-slate-700 border-slate-200',
        'primary' => 'bg-sky-50 text-sky-700 border-sky-200',
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
        'danger' => 'bg-rose-50 text-rose-700 border-rose-200',
        'coin' => 'bg-amber-100 text-amber-900 border-amber-300 font-bold',
    ];

    $dotColors = [
        'slate' => 'bg-slate-400',
        'primary' => 'bg-sky-500',
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'coin' => 'bg-amber-600',
    ];

    $classes = 'inline-flex items-center gap-1.5 rounded-full border ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['slate']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColors[$variant] ?? 'bg-slate-400' }}"></span>
    @endif
    {{ $slot }}
</span>
