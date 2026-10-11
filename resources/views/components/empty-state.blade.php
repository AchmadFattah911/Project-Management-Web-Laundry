@props([
    'title' => 'Belum ada data',
    'description' => 'Data yang Anda cari tidak ditemukan atau belum ditambahkan.',
    'action' => null,
])

<div {{ $attributes->merge(['class' => 'p-12 text-center flex flex-col items-center justify-center']) }}>
    <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-4">
        {{ $slot->isNotEmpty() ? $slot : '' }}
        @if ($slot->isEmpty())
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        @endif
    </div>
    <h4 class="text-base font-semibold text-slate-800 mb-1">{{ $title }}</h4>
    <p class="text-xs text-slate-500 max-w-sm mb-5 leading-relaxed">{{ $description }}</p>
    @if ($action)
        <div>
            {{ $action }}
        </div>
    @endif
</div>
