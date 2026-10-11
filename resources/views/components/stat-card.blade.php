@props([
    'title',
    'value',
    'hint' => null,
    'icon' => null,
    'color' => 'sky', // sky, amber, emerald, violet, rose
    'trend' => null, // e.g. "+12%" or text
    'trendUp' => true,
])

@php
    $colorMap = [
        'sky' => 'bg-sky-50 text-sky-600 border-sky-100',
        'amber' => 'bg-amber-50 text-amber-600 border-amber-100',
        'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
        'violet' => 'bg-violet-50 text-violet-600 border-violet-100',
        'rose' => 'bg-rose-50 text-rose-600 border-rose-100',
    ];

    $badgeClass = $colorMap[$color] ?? $colorMap['sky'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs hover:border-slate-300 transition-all duration-150 flex flex-col justify-between']) }}>
    <div class="flex items-start justify-between gap-3 mb-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $title }}</p>
        @if ($icon)
            <div class="p-2 rounded-lg border {{ $badgeClass }}">
                {{ $icon }}
            </div>
        @endif
    </div>

    <div>
        <div class="flex items-baseline gap-2">
            <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">{{ $value }}</h3>
            @if ($trend)
                <span class="text-xs font-semibold {{ $trendUp ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $trend }}
                </span>
            @endif
        </div>

        @if ($hint)
            <p class="text-xs text-slate-500 mt-1.5 flex items-center gap-1.5">
                {{ $hint }}
            </p>
        @endif
    </div>
</div>
