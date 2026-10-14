@extends('layouts.guest')

@section('title', 'Konfirmasi Kata Sandi - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-md px-4 sm:px-6">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-slate-900 shadow-md mb-3">
                <x-icon name="washer" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Konfirmasi Keamanan
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                Ini adalah area aman. Harap masukkan kata sandi Anda sebelum melanjutkan.
            </p>
        </div>

        <x-card class="shadow-sm">
            <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
                @csrf

                <!-- Password -->
                <x-input
                    label="Kata Sandi Anda"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <div class="pt-2">
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Konfirmasi Lanjutkan
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
