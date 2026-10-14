@extends('layouts.guest')

@section('title', 'Verifikasi Email - Laundry Coin')

@section('content')
<div class="py-12 sm:py-16 bg-slate-50 min-h-[calc(100vh-140px)] flex items-center justify-center">
    <div class="w-full max-w-md px-4 sm:px-6">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-sky-600 text-white shadow-md mb-3">
                <x-icon name="washer" class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Verifikasi Email Anda
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                Terima kasih telah mendaftar! Sebelum memulai, silakan klik tautan verifikasi yang baru saja kami kirimkan ke email Anda.
            </p>
        </div>

        <x-card class="shadow-sm">
            @if (session('status') == 'verification-link-sent')
                <x-alert type="success" message="Tautan verifikasi baru telah berhasil dikirim ke alamat email Anda." class="mb-5" />
            @endif

            <div class="space-y-4">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button type="submit" variant="primary" size="lg" class="w-full rounded-xl">
                        Kirim Ulang Email Verifikasi
                    </x-button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="text-center pt-2">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-semibold underline cursor-pointer">
                        Keluar dari Akun (Logout)
                    </button>
                </form>
            </div>
        </x-card>
    </div>
</div>
@endsection
