@props([
    'value' => null,
    'required' => false,
])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-rose-500 font-bold">*</span>
    @endif
</label>
