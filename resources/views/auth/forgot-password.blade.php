@extends('layouts.guest')

@section('title', 'Lupa Kata Sandi - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-md px-4 sm:px-6">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-slate-900 shadow-md mb-3">
                <x-icon name="clock" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Lupa Kata Sandi?
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-1.5 max-w-xs mx-auto">
                Masukkan email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
            </p>
        </div>

        <x-card class="shadow-sm">
            @if (session('status'))
                <x-alert type="success" :message="session('status')" class="mb-5" />
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <!-- Email -->
                <x-input
                    label="Email Terdaftar"
                    name="email"
                    type="email"
                    :value="old('email')"
                    placeholder="nama@email.com"
                    required
                    autofocus
                    autocomplete="username"
                />

                <div class="pt-2">
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Kirim Tautan Pemulihan
                    </x-button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                Kembali ke
                <a href="{{ route('login') }}" class="text-sky-600 hover:text-sky-700 font-bold hover:underline ml-1">
                    Halaman Masuk
                </a>
            </div>
        </x-card>
    </div>
</div>
@endsection
