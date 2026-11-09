@props([
    'variant' => 'primary', // primary | danger | secondary | success
])

@php
    $classes = [
        'primary'   => 'bg-sky-600 hover:bg-sky-700 text-white',
        'danger'    => 'bg-rose-600 hover:bg-rose-700 text-white',
        'success'   => 'bg-emerald-600 hover:bg-emerald-700 text-white',
        'secondary' => 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50',
    ][$variant] ?? 'bg-sky-600 hover:bg-sky-700 text-white';
@endphp

{{-- Tombol submit di dalam <x-panel.form>: nonaktif + spinner selama form dikirim --}}
<button
    type="submit"
    :disabled="sending"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold shadow-xs transition-colors disabled:opacity-60 disabled:cursor-wait {$classes}"]) }}
>
    <svg x-show="sending" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span>{{ $slot }}</span>
</button>
