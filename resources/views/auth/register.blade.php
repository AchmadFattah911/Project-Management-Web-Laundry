@extends('layouts.guest')

@section('title', 'Daftar Akun Pelanggan - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-lg px-4 sm:px-6">
        
        <!-- Header Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-sky-600 text-white shadow-md mb-3">
                <x-icon name="washer" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Buat Akun Pelanggan
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-1.5">
                Daftar sekarang untuk melacak pesanan dan kemudahan layanan laundry.
            </p>
        </div>

        <!-- Register Card -->
        <x-card class="shadow-sm">
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <!-- Nama Lengkap -->
                <x-input
                    label="Nama Lengkap"
                    name="name"
                    type="text"
                    :value="old('name')"
                    placeholder="Contoh: Budi Santoso"
                    required
                    autofocus
                    autocomplete="name"
                />

                <!-- Email -->
                <x-input
                    label="Alamat Email"
                    name="email"
                    type="email"
                    :value="old('email')"
                    placeholder="nama@email.com"
                    required
                    autocomplete="email"
                />

                <!-- Nomor Telepon (Dipertahankan, address dihapus) -->
                <x-input
                    label="Nomor Telepon / WhatsApp"
                    name="phone"
                    type="tel"
                    :value="old('phone')"
                    placeholder="081234567890"
                    autocomplete="tel"
                    hint="Digunakan untuk pemberitahuan saat cucian selesai."
                />

                <!-- Password -->
                <x-input
                    label="Kata Sandi"
                    name="password"
                    type="password"
                    placeholder="Minimal 8 karakter"
                    required
                    autocomplete="new-password"
                />

                <!-- Password Confirmation -->
                <x-input
                    label="Konfirmasi Kata Sandi"
                    name="password_confirmation"
                    type="password"
                    placeholder="Ketik ulang kata sandi"
                    required
                    autocomplete="new-password"
                />

                <!-- Submit Button -->
                <div class="pt-3">
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Daftar Sebagai Pelanggan
                    </x-button>
                </div>
            </form>

            <!-- Bottom Login Link -->
            <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                Sudah memiliki akun?
                <a href="{{ route('login') }}" class="text-sky-600 hover:text-sky-700 font-bold hover:underline ml-1">
                    Masuk di sini
                </a>
            </div>
        </x-card>
    </div>
</div>
@endsection
