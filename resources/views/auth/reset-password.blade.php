@extends('layouts.guest')

@section('title', 'Atur Ulang Kata Sandi - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-md px-4 sm:px-6">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-sky-600 text-white shadow-md mb-3">
                <x-icon name="check" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Atur Kata Sandi Baru
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-1.5">
                Silakan buat kata sandi baru untuk akun Anda.
            </p>
        </div>

        <x-card class="shadow-sm">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $token ?? '' }}">

                <!-- Email Address -->
                <x-input
                    label="Alamat Email"
                    name="email"
                    type="email"
                    :value="old('email', $email ?? '')"
                    required
                    autofocus
                    autocomplete="username"
                />

                <!-- Password -->
                <x-input
                    label="Kata Sandi Baru"
                    name="password"
                    type="password"
                    placeholder="Minimal 8 karakter"
                    required
                    autocomplete="new-password"
                />

                <!-- Confirm Password -->
                <x-input
                    label="Konfirmasi Kata Sandi Baru"
                    name="password_confirmation"
                    type="password"
                    placeholder="Ketik ulang kata sandi baru"
                    required
                    autocomplete="new-password"
                />

                <div class="pt-2">
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Simpan Kata Sandi Baru
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection
