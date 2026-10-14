<section>
    <header class="mb-5">
        <h2 class="text-base font-bold text-slate-900 tracking-tight">
            Perbarui Kata Sandi
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            Pastikan akun Anda menggunakan kata sandi yang panjang dan acak demi keamanan data.
        </p>
    </header>

    @if (session('status') === 'password-updated')
        <x-alert type="success" message="Kata sandi berhasil diperbarui." class="mb-5" />
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 max-w-xl">
        @csrf
        @method('put')

        <!-- Kata Sandi Saat Ini -->
        <x-input
            label="Kata Sandi Saat Ini"
            name="current_password"
            type="password"
            placeholder="••••••••"
            autocomplete="current-password"
        />

        <!-- Kata Sandi Baru -->
        <x-input
            label="Kata Sandi Baru"
            name="password"
            type="password"
            placeholder="Minimal 8 karakter"
            autocomplete="new-password"
        />

        <!-- Konfirmasi Kata Sandi Baru -->
        <x-input
            label="Konfirmasi Kata Sandi Baru"
            name="password_confirmation"
            type="password"
            placeholder="Ketik ulang kata sandi baru"
            autocomplete="new-password"
        />

        <div class="pt-2">
            <x-button type="submit" variant="primary" size="md">
                Perbarui Kata Sandi
            </x-button>
        </div>
    </form>
</section>
