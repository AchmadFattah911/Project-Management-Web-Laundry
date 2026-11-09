{{-- Pelanggan tidak punya dashboard: profil tampil bergaya aplikasi mobile seperti halaman Akun --}}
@php $isPelanggan = auth()->user()->isPelanggan(); @endphp

@extends($isPelanggan ? 'layouts.mobile' : 'layouts.dashboard', ['bottomNavActive' => 'akun'])

@section('title', 'Pengaturan Profil')

@section('content')
@if ($isPelanggan)
    @include('pelanggan.partials.page-header', ['title' => 'Pengaturan Profil', 'back' => route('pelanggan.akun')])
@endif

<div class="{{ $isPelanggan ? 'px-4 py-4 space-y-4' : 'max-w-4xl mx-auto space-y-6' }}">
    @unless ($isPelanggan)
        <!-- Breadcrumb / Header Title -->
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Pengaturan Profil & Keamanan</h2>
            <p class="text-xs text-slate-500 mt-1">Kelola data profil, kontak, dan kata sandi akun Anda.</p>
        </div>
    @endunless

    <!-- 1. Form Update Profile Information -->
    <x-card>
        @include('profile.partials.update-profile-information-form')
    </x-card>

    <!-- 2. Form Update Password -->
    <x-card>
        @include('profile.partials.update-password-form')
    </x-card>

    <!-- 3. Form Delete Account -->
    <x-card>
        @include('profile.partials.delete-user-form')
    </x-card>
</div>
@endsection
