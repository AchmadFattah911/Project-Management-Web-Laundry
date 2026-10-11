<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - Laundry Coin' : 'Laundry Coin - Solusi Cuci Koin Modern, Bersih & Cepat' }}</title>
    <meta name="description" content="Laundry Coin adalah laundromat koin modern dengan mesin cuci & pengering berkecepatan tinggi, higienis, dan praktis. Tersedia layanan mandiri serta drop-off kiloan.">

    <!-- Fonts: Plus Jakarta Sans (Heading) & DM Sans (Body) via Bunny Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Favicon (Washing Machine Icon SVG) -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284C7'><rect x='3' y='2' width='18' height='20' rx='3'/><circle cx='12' cy='14' r='5' fill='white'/></svg>">

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans min-h-screen flex flex-col selection:bg-sky-200 selection:text-sky-900">
    <!-- Main Public Navbar -->
    <x-navbar />

    <!-- Session Alerts if present -->
    @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
            <x-alert type="success" :message="session('success')" />
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
            <x-alert type="error" :message="session('error')" />
        </div>
    @endif

    <!-- Main Content Slot -->
    <main class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Main Public Footer -->
    <x-footer />
</body>
</html>
