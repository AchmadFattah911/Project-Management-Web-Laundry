<section>
    <header class="mb-5">
        <h2 class="text-base font-bold text-slate-900 tracking-tight">
            Informasi Profil Pengguna
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            Perbarui data profil akun, alamat email, dan nomor kontak Anda.
        </p>
    </header>

    @if (session('status') === 'profile-updated')
        <x-alert type="success" message="Informasi profil berhasil disimpan." class="mb-5" />
    @endif

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 max-w-xl">
        @csrf
        @method('patch')

        <!-- Nama -->
        <x-input
            label="Nama Lengkap"
            name="name"
            type="text"
            :value="old('name', $user->name)"
            required
            autofocus
            autocomplete="name"
        />

        <!-- Email -->
        <x-input
            label="Alamat Email"
            name="email"
            type="email"
            :value="old('email', $user->email)"
            required
            autocomplete="email"
        />

        <!-- Nomor Telepon (Dipertahankan) -->
        <x-input
            label="Nomor Telepon / WhatsApp"
            name="phone"
            type="tel"
            :value="old('phone', $user->phone)"
            placeholder="081234567890"
            autocomplete="tel"
            hint="Nomor aktif untuk menerima notifikasi status cucian."
        />

        @if ($mustVerifyEmail && $user->email_verified_at === null)
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-2">
                <p>Alamat email Anda belum diverifikasi.</p>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="font-bold underline text-amber-900 hover:text-amber-700 cursor-pointer">
                        Klik di sini untuk mengirim ulang email verifikasi.
                    </button>
                </form>
            </div>
        @endif

        <div class="pt-2">
            <x-button type="submit" variant="primary" size="md">
                Simpan Perubahan
            </x-button>
        </div>
    </form>
</section>
