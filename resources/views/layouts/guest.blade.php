<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Laundry Coin - Self Laundry & Laundry Drop-Off Cashless')</title>
    <meta name="description" content="@yield('meta_description', 'Laundry Coin adalah laundromat modern dengan mesin cuci & pengering berkecepatan tinggi, higienis, dan praktis. Tersedia self laundry langsung di outlet serta layanan drop-off yang bisa dipesan online dengan pembayaran cashless.')">

    <!-- Fonts: Plus Jakarta Sans (Display) & DM Sans (Body) via Bunny Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284C7'><rect x='3' y='2' width='18' height='20' rx='3'/><circle cx='12' cy='14' r='5' fill='white'/></svg>">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans min-h-screen flex flex-col selection:bg-sky-200 selection:text-sky-900">
    <!-- Public Navbar Component -->
    <x-navbar />

    <!-- Flash Session Alerts -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 w-full">
        @if (session('success'))
            <x-alert type="success" :message="session('success')" class="mb-4" />
        @endif

        @if (session('error'))
            <x-alert type="error" :message="session('error')" class="mb-4" />
        @endif

        @if (session('status'))
            <x-alert type="info" :message="session('status')" class="mb-4" />
        @endif
    </div>

    <!-- Main Content Slot -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Public Footer Component -->
    <x-footer />

    @stack('scripts')
</body>
</html>
