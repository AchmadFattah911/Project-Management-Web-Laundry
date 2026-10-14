@props([
    'name' => 'payment_method',
    'selected' => null,
    'idPrefix' => 'pm',
])

@php
    $methods = config('laundry.online_payment_methods', []);
    $selected = old($name, $selected ?? array_key_first($methods));
@endphp

<fieldset {{ $attributes }}>
    <legend class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
        Metode Pembayaran (Cashless) <span class="text-rose-500">*</span>
    </legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($methods as $key => $method)
            <label for="{{ $idPrefix }}-{{ $key }}" class="flex items-start gap-2.5 rounded-xl border border-slate-300 p-3 cursor-pointer hover:border-sky-400 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50/70 transition-colors">
                <input
                    id="{{ $idPrefix }}-{{ $key }}"
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $key }}"
                    class="mt-0.5 text-sky-600 focus:ring-sky-500"
                    required
                    @checked($selected === $key)
                />
                <span>
                    <span class="block text-xs font-bold text-slate-900">{{ $method['label'] }}</span>
                    <span class="block text-[11px] text-slate-500 leading-snug">{{ $method['description'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</fieldset>
