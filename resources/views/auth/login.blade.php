@extends('layouts.guest')

@section('title', 'Masuk ke Akun - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-md px-4 sm:px-6">
        
        <!-- Header Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-sky-600 text-white shadow-md mb-3">
                <x-icon name="washer" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Selamat Datang Kembali
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-1.5">
                Masuk ke panel Laundry Coin untuk mengelola cucian Anda.
            </p>
        </div>

        <!-- Login Card -->
        <x-card class="shadow-sm">
            @if (session('status'))
                <x-alert type="success" :message="session('status')" class="mb-5" />
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <!-- Email Input -->
                <x-input
                    label="Email"
                    name="email"
                    type="email"
                    :value="old('email')"
                    placeholder="nama@email.com"
                    required
                    autofocus
                    autocomplete="username"
                />

                <!-- Password Input -->
                <x-input
                    label="Kata Sandi"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="remember"
                            class="rounded border-slate-300 text-sky-600 shadow-xs focus:ring-sky-500"
                        />
                        <span class="text-slate-600 font-medium">Ingat saya</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sky-600 hover:text-sky-700 font-semibold hover:underline">
                            Lupa kata sandi?
                        </a>
                    @endif
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Masuk ke Akun
                    </x-button>
                </div>
            </form>

            <!-- Bottom Register Link -->
            <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                Belum memiliki akun?
                <a href="{{ route('register') }}" class="text-sky-600 hover:text-sky-700 font-bold hover:underline ml-1">
                    Daftar Sekarang
                </a>
            </div>
        </x-card>
    </div>
</div>
@endsection
