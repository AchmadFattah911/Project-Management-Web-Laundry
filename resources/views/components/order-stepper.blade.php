@props([
    'current' => 'pilih', // pilih, detail, bayar, selesai
])

@php
    $steps = [
        'pilih'  => 'Pilih Layanan',
        'detail' => 'Detail & Konfirmasi',
        'bayar'  => 'Pembayaran',
    ];
    $keys = array_keys($steps);
    // "selesai" = semua langkah tercentang
    $currentIndex = $current === 'selesai' ? count($keys) : array_search($current, $keys, true);
@endphp

<ol {{ $attributes->merge(['class' => 'flex items-center gap-2 text-xs font-bold']) }} aria-label="Tahapan pemesanan">
    @foreach ($steps as $key => $label)
        @php
            $i = $loop->index;
            $state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'next');
        @endphp
        <li class="flex items-center gap-2" @if ($state === 'current') aria-current="step" @endif>
            <span @class([
                'w-7 h-7 rounded-full flex items-center justify-center shrink-0',
                'bg-emerald-500 text-white' => $state === 'done',
                'bg-sky-600 text-white ring-4 ring-sky-100' => $state === 'current',
                'bg-slate-200 text-slate-500' => $state === 'next',
            ])>
                @if ($state === 'done') <x-icon name="check" class="w-3.5 h-3.5" /> @else {{ $i + 1 }} @endif
            </span>
            <span @class(['hidden sm:inline', 'text-slate-900' => $state === 'current', 'text-slate-500' => $state !== 'current'])>{{ $label }}</span>
            @if (! $loop->last)
                <span class="w-6 h-px bg-slate-300"></span>
            @endif
        </li>
    @endforeach
</ol>
