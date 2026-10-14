@extends('layouts.dashboard')

@section('title', 'Pengaturan Profil')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Header Title -->
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Pengaturan Profil & Keamanan</h2>
        <p class="text-xs text-slate-500 mt-1">Kelola data profil, kontak, dan kata sandi akun Anda.</p>
    </div>

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
