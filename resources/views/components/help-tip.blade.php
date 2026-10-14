@props([
    'text' => '',
    'label' => 'Bantuan',
])

{{-- Tombol "?" kecil dengan penjelasan singkat. Muncul saat hover/fokus (desktop) atau ketuk (mobile). --}}
<span
    x-data="{ show: false }"
    x-on:mouseenter="show = true"
    x-on:mouseleave="show = false"
    x-on:click.outside="show = false"
    x-on:keydown.escape="show = false"
    {{ $attributes->merge(['class' => 'relative inline-flex align-middle']) }}
>
    <button
        type="button"
        x-on:click="show = !show"
        x-on:focus="show = true"
        x-on:blur="show = false"
        class="w-4 h-4 rounded-full text-slate-400 hover:text-sky-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
        aria-label="{{ $label }}"
    >
        <x-icon name="question" class="w-4 h-4" />
    </button>
    <span
        x-show="show"
        x-cloak
        x-transition.opacity
        role="tooltip"
        class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 z-30 w-60 rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-medium normal-case tracking-normal leading-snug text-white shadow-lg"
    >
        {{ $text }}
    </span>
</span>
