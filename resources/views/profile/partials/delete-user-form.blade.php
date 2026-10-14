<section class="space-y-4">
    <header>
        <h2 class="text-base font-bold text-red-600 tracking-tight">
            Hapus Akun Pengguna
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            Setelah akun Anda dihapus, seluruh data dan riwayat pesanan akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
        </p>
    </header>

    <x-button
        type="button"
        variant="danger"
        size="md"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        Hapus Akun Ini
    </x-button>

    <x-modal name="confirm-user-deletion" maxWidth="lg">
        <form method="POST" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <div class="flex items-center gap-3 text-red-600 mb-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                    <x-icon name="x" class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 leading-tight">Konfirmasi Hapus Akun</h3>
                    <p class="text-xs text-slate-500">Tindakan ini permanen dan tidak dapat dipulihkan.</p>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                Masukkan kata sandi akun Anda untuk mengonfirmasi bahwa Anda adalah pemilik sah akun ini.
            </p>

            <x-input
                label="Kata Sandi Konfirmasi"
                name="password"
                type="password"
                placeholder="Masukkan kata sandi Anda"
                required
            />

            <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100">
                <x-button
                    type="button"
                    variant="outline"
                    size="md"
                    x-on:click="$dispatch('close')"
                >
                    Batal
                </x-button>

                <x-button
                    type="submit"
                    variant="danger"
                    size="md"
                >
                    Hapus Akun Permanen
                </x-button>
            </div>
        </form>
    </x-modal>
</section>
