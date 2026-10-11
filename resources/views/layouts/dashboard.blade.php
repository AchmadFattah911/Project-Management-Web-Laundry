@props([
    'title' => 'Dashboard',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} - Laundry Coin</title>

    <!-- Fonts: Plus Jakarta Sans & DM Sans via Bunny Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|dm-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230284C7'><rect x='3' y='2' width='18' height='20' rx='3'/><circle cx='12' cy='14' r='5' fill='white'/></svg>">

    <!-- Chart.js via CDN for responsive dashboard charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{ sidebarOpen: false }"
    class="h-full font-sans text-slate-800 antialiased bg-slate-50 selection:bg-sky-200 selection:text-sky-900"
>
    <div class="min-h-screen flex flex-col">
        <!-- Sidebar Component -->
        <x-sidebar />

        <!-- Main Wrapper (Offset for LG fixed sidebar) -->
        <div class="lg:pl-64 flex flex-col flex-1 min-w-0">
            <!-- Topbar Header -->
            <x-topbar :title="$title" />

            <!-- Flash Alerts -->
            <div class="px-4 sm:px-6 lg:px-8 pt-4">
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

            <!-- Page Content -->
            <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                {{ $slot ?? '' }}
                @yield('content')
            </main>

            <!-- Dashboard Mini Footer -->
            <footer class="px-4 sm:px-6 lg:px-8 py-4 border-t border-slate-200/80 bg-white text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} Laundry Coin - Solusi Laundromat Modern</span>
                <span class="text-slate-400">Versi 2.0 (Blade MVC)</span>
            </footer>
        </div>
    </div>
</body>
</html>
