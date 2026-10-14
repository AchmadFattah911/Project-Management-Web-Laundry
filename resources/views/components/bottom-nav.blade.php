@props([
    'active' => null, // beranda | favorit | transaksi | bantuan | akun
])

@php
    $items = [
        'beranda' => [
            'label' => 'Beranda',
            'href'  => route('home'),
            'icon'  => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
        // Belum ada fitur favorit: diarahkan ke katalog layanan di landing page
        'favorit' => [
            'label' => 'Favorit',
            'href'  => route('home') . '#layanan',
            'icon'  => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        ],
        'transaksi' => [
            'label' => 'Transaksi',
            'href'  => route('pelanggan.orders.index'),
            'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        'bantuan' => [
            'label' => 'Bantuan',
            'href'  => route('home') . '#faq',
            'icon'  => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'akun' => [
            'label' => 'Akun',
            'href'  => route('pelanggan.akun'),
            'icon'  => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        ],
    ];
@endphp

<nav
    class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200"
    style="padding-bottom: env(safe-area-inset-bottom, 0px);"
    aria-label="Navigasi utama"
>
    <ul class="max-w-3xl mx-auto grid grid-cols-5">
        @foreach ($items as $key => $item)
            @php $isActive = $active === $key; @endphp
            <li>
                <a
                    href="{{ $item['href'] }}"
                    @if ($isActive) aria-current="page" @endif
                    class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-semibold transition-colors {{ $isActive ? 'text-sky-600' : 'text-slate-500 hover:text-sky-600' }}"
                >
                    <svg class="w-6 h-6" fill="{{ $isActive ? 'currentColor' : 'none' }}" fill-opacity="{{ $isActive ? '0.15' : '0' }}" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $isActive ? '2.2' : '1.8' }}" d="{{ $item['icon'] }}" />
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
