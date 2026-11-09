@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

{{-- Input sederhana dengan label + pesan error validasi (old() otomatis) --}}
<label class="block text-xs">
    <span class="font-bold text-slate-700">{{ $label }} @if ($required)<span class="text-rose-600">*</span>@endif</span>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded-xl border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500' . ($errors->has($name) ? ' border-rose-400' : '')]) }}
    />
    @if ($hint)
        <span class="mt-1 block text-[11px] text-slate-500">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="mt-1 block text-[11px] font-semibold text-rose-600">{{ $message }}</span>
    @enderror
</label>
