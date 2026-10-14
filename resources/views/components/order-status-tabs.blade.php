@props([
    'active' => 'semua',
    'route',            // nama route tujuan tab (mis. pelanggan.akun / pelanggan.orders.index)
    'counts' => [],     // opsional: jumlah pesanan per tab
    'fragment' => '',   // opsional: anchor agar halaman tetap di posisi riwayat setelah pindah tab
])

@php
    $tabs = [
        'semua'    => 'Semua',
        'diproses' => 'Di proses',
        'selesai'  => 'Selesai',
    ];
@endphp

<nav {{ $attributes->merge(['class' => 'flex gap-2 overflow-x-auto -mx-1 px-1 pb-1']) }} aria-label="Filter status pesanan">
    @foreach ($tabs as $key => $label)
        @php
            $isActive = $active === $key;
            $url = route($route, $key === 'semua' ? [] : ['status' => $key]) . ($fragment ? '#' . $fragment : '');
        @endphp
        <a
            href="{{ $url }}"
            @if ($isActive) aria-current="page" @endif
            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold border transition-colors {{ $isActive ? 'bg-sky-600 border-sky-600 text-white' : 'bg-white border-slate-200 text-slate-600 hover:border-sky-300 hover:text-sky-700' }}"
        >
            <span>{{ $label }}</span>
            @isset($counts[$key])
                <span class="min-w-5 px-1.5 rounded-full text-[10px] leading-4 {{ $isActive ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $counts[$key] }}</span>
            @endisset
        </a>
    @endforeach
</nav>
