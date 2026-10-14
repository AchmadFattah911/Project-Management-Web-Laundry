@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'rows' => 3,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
])

@php
    $id = $attributes->get('id') ?? $name ?? 'textarea-' . uniqid();
    $hasError = $name ? $errors->has($name) : !empty($error);
    $errorMessage = $name ? $errors->first($name) : $error;

    $borderClass = $hasError
        ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-200'
        : 'border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-sky-100';
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500 font-bold">*</span>
            @endif
        </label>
    @endif

    <div class="relative rounded-lg shadow-xs">
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->except('class')->merge([
                'class' => "block w-full rounded-lg border text-sm transition-all duration-150 py-2.5 px-3.5 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-500 {$borderClass}"
            ]) }}
        >{{ old($name, $value ?? $slot) }}</textarea>
    </div>

    @if ($hasError)
        <p class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span>{{ $errorMessage }}</span>
        </p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
