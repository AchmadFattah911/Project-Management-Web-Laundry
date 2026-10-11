@props([
    'variant' => 'primary', // primary, secondary, outline, danger, ghost
    'size' => 'md', // sm, md, lg
    'type' => 'button',
    'href' => null,
    'loading' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed select-none active:scale-[0.98]';

    $sizes = [
        'sm' => 'text-xs px-3 py-1.5 gap-1.5',
        'md' => 'text-sm px-4 py-2.5 gap-2',
        'lg' => 'text-base px-5 py-3 gap-2.5 font-semibold',
    ];

    $variants = [
        'primary' => 'bg-sky-600 hover:bg-sky-700 text-white shadow-sm hover:shadow focus-visible:ring-sky-500',
        'secondary' => 'bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold shadow-sm hover:shadow focus-visible:ring-amber-400',
        'outline' => 'border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 focus-visible:ring-sky-500',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-sm focus-visible:ring-red-500',
        'ghost' => 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus-visible:ring-slate-400',
    ];

    $classes = $baseClasses . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
