<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0284C7">

    <title>@yield('title', 'Akun') - {{ config('app.name', 'Laundry Coin') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284C7'><rect x='3' y='2' width='18' height='20' rx='3'/><circle cx='12' cy='14' r='5' fill='white'/></svg>">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans text-slate-800 antialiased bg-slate-100">
    {{-- Layout gaya aplikasi mobile (halaman Akun pelanggan): konten di tengah + bottom navigation --}}
    <div class="max-w-3xl mx-auto min-h-screen bg-slate-50 shadow-sm pb-24">
        @if (session('success') || session('error') || session('status'))
            <div class="px-4 pt-4 space-y-2">
                @if (session('success'))
                    <x-alert type="success" :message="session('success')" />
                @endif
                @if (session('error'))
                    <x-alert type="error" :message="session('error')" />
                @endif
                @if (session('status'))
                    <x-alert type="info" :message="session('status')" />
                @endif
            </div>
        @endif

        @yield('content')
    </div>

    <x-bottom-nav :active="$bottomNavActive ?? null" />
</body>
</html>
