@props([
    'title',
    'subtitle' => null,
])

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim((string) $slot) !== '')
        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">{{ $slot }}</div>
    @endif
</div>
