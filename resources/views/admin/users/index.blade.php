@extends('layouts.dashboard')

@section('title', 'Manajemen Pengguna & Staf')

@section('content')
<div
    x-data="{
        addUserModalOpen: false,
        editUserModalOpen: false,
        selectedUser: null,
        currentUserId: {{ auth()->id() }},
        openEdit(user) {
            this.selectedUser = user;
            this.editUserModalOpen = true;
        }
    }"
    class="space-y-6"
>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Manajemen Akun & Peran Pengguna
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Kelola hak akses administrator, staf kasir/operasional pegawai, dan data pelanggan
            </p>
        </div>

        @can('create', App\Models\User::class)
            <button
                type="button"
                @click="addUserModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all self-start sm:self-auto"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Pengguna</span>
            </button>
        @endcan
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Akun"
            :value="$stats['total'] ?? 0"
            hint="Seluruh user terdaftar"
            color="slate"
        />
        <x-stat-card
            title="Administrator"
            :value="$stats['admin'] ?? 0"
            hint="Hak akses penuh sistem"
            color="rose"
        />
        <x-stat-card
            title="Pegawai / Staf"
            :value="$stats['pegawai'] ?? 0"
            hint="Operator mesin & kasir"
            color="amber"
        />
        <x-stat-card
            title="Pelanggan"
            :value="$stats['pelanggan'] ?? 0"
            hint="Member laundry coin"
            color="sky"
        />
    </div>

    <!-- Toolbar Pencarian & Filter -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-7 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Cari nama, email, atau nomor telepon..."
                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                />
            </div>

            <div class="sm:col-span-3">
                <select
                    name="role"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                >
                    <option value="">Semua Peran (Role)</option>
                    <option value="admin" {{ ($role ?? '') === 'admin' ? 'selected' : '' }}>Administrator</option>
                    <option value="pegawai" {{ ($role ?? '') === 'pegawai' ? 'selected' : '' }}>Pegawai Outlet</option>
                    <option value="pelanggan" {{ ($role ?? '') === 'pelanggan' ? 'selected' : '' }}>Pelanggan</option>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors"
                >
                    Filter
                </button>
                @if (($search ?? '') || ($role ?? ''))
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors"
                        title="Reset Filter"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Tabel Daftar Pengguna -->
    <x-card padding="p-0">
        @if ($users->isNotEmpty())
            <x-table :headers="['Nama Pengguna', 'Email & Kontak', 'Peran / Role', 'Tanggal Gabung', 'Aksi']">
                @foreach ($users as $userItem)
                    @php
                        $userRoleStr = $userItem->role instanceof \App\Enums\UserRole ? $userItem->role->value : (string) $userItem->role;
                        $hasOrders = $userItem->orders()->exists();
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 font-extrabold text-xs flex items-center justify-center uppercase">
                                    {{ substr($userItem->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                        <span>{{ $userItem->name }}</span>
                                        @if ($userItem->id === auth()->id())
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-sky-100 text-sky-800 font-extrabold">Anda</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500">ID #{{ $userItem->id }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-5 py-4 text-xs text-slate-700">
                            <div class="font-medium text-slate-900">{{ $userItem->email }}</div>
                            <div class="text-[11px] text-slate-500">{{ $userItem->phone ?: 'Tanpa nomor HP' }}</div>
                        </td>

                        <td class="px-5 py-4">
                            @if ($userRoleStr === 'admin')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-extrabold bg-rose-50 text-rose-700 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    <span>Administrator</span>
                                </span>
                            @elseif ($userRoleStr === 'pegawai')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-extrabold bg-amber-50 text-amber-800 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>Pegawai</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                    <span>Pelanggan</span>
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-xs text-slate-500">
                            {{ $userItem->created_at->format('d M Y') }}
                        </td>

                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                @can('update', $userItem)
                                    <button
                                        type="button"
                                        @click="openEdit({{ Js::from([
                                            'id' => $userItem->id,
                                            'name' => $userItem->name,
                                            'email' => $userItem->email,
                                            'phone' => $userItem->phone,
                                            'role' => $userRoleStr,
                                        ]) }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs transition-colors"
                                    >
                                        Edit
                                    </button>
                                @endcan

                                @if ($userItem->id !== auth()->id())
                                    <form
                                        action="{{ route('admin.users.destroy', $userItem) }}"
                                        method="POST"
                                        class="inline-block"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun {{ $userItem->name }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            @if ($hasOrders) disabled title="User memiliki pesanan dan tidak boleh dihapus" @endif
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors
                                                {{ $hasOrders ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-rose-50 hover:bg-rose-100 text-rose-700' }}
                                            "
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="p-4 border-t border-slate-200/80">
                {{ $users->links() }}
            </div>
        @else
            <x-empty-state
                title="Tidak Ada Pengguna Ditemukan"
                description="Tidak ada data akun yang sesuai dengan pencarian atau filter peran yang dipilih."
            />
        @endif
    </x-card>

    <!-- Modal Tambah Pengguna Baru -->
    <div
        x-show="addUserModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="addUserModalOpen"
                @click="addUserModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="addUserModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <div class="px-6 py-5 bg-gradient-to-r from-sky-900 to-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold">Tambah Pengguna Baru</h3>
                        <p class="text-xs text-sky-200">Buat akun staf atau pelanggan secara manual</p>
                    </div>
                    <button
                        type="button"
                        @click="addUserModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form action="{{ route('admin.users.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label for="new-name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="new-name"
                            type="text"
                            name="name"
                            required
                            placeholder="Contoh: Budi Santoso"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        />
                    </div>

                    <div>
                        <label for="new-email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Alamat Email <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="new-email"
                            type="email"
                            name="email"
                            required
                            placeholder="nama@email.com"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        />
                    </div>

                    <div>
                        <label for="new-phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nomor Telepon / WhatsApp
                        </label>
                        <input
                            id="new-phone"
                            type="tel"
                            name="phone"
                            placeholder="0812xxxxxxxx"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        />
                    </div>

                    <div>
                        <label for="new-password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Password Awal <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="new-password"
                            type="password"
                            name="password"
                            required
                            minlength="8"
                            placeholder="Minimal 8 karakter"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        />
                    </div>

                    <div>
                        <label for="new-role" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Peran Pengguna (Role) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="new-role"
                            name="role"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        >
                            <option value="pelanggan">Pelanggan (Customer)</option>
                            <option value="pegawai">Pegawai Outlet (Staf / Kasir)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="addUserModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs transition-colors"
                        >
                            Simpan Pengguna
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pengguna -->
    <div
        x-show="editUserModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="editUserModalOpen"
                @click="editUserModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="editUserModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <template x-if="selectedUser">
                    <div>
                        <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-sky-900 text-white flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold">Edit Akun Pengguna</h3>
                                <p class="text-xs text-sky-200" x-text="selectedUser.name + ' (#' + selectedUser.id + ')'"></p>
                            </div>
                            <button
                                type="button"
                                @click="editUserModalOpen = false"
                                class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form :action="'/admin/users/' + selectedUser.id" method="POST" class="p-6 space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="edit-name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nama Lengkap <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="edit-name"
                                    type="text"
                                    name="name"
                                    :value="selectedUser.name"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div>
                                <label for="edit-email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Alamat Email <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="edit-email"
                                    type="email"
                                    name="email"
                                    :value="selectedUser.email"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div>
                                <label for="edit-phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nomor Telepon
                                </label>
                                <input
                                    id="edit-phone"
                                    type="tel"
                                    name="phone"
                                    :value="selectedUser.phone"
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div>
                                <label for="edit-password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Password Baru (Kosongkan jika tidak diubah)
                                </label>
                                <input
                                    id="edit-password"
                                    type="password"
                                    name="password"
                                    minlength="8"
                                    placeholder="Isi hanya jika ingin mereset password"
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div>
                                <label for="edit-role" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Peran Pengguna (Role) <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="edit-role"
                                    name="role"
                                    required
                                    :disabled="selectedUser.id === currentUserId"
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500 disabled:bg-slate-100 disabled:text-slate-500"
                                >
                                    <option value="pelanggan" :selected="selectedUser.role === 'pelanggan'">Pelanggan (Customer)</option>
                                    <option value="pegawai" :selected="selectedUser.role === 'pegawai'">Pegawai Outlet (Staf / Kasir)</option>
                                    <option value="admin" :selected="selectedUser.role === 'admin'">Administrator (Akses Penuh)</option>
                                </select>
                                <template x-if="selectedUser.id === currentUserId">
                                    <p class="text-[11px] text-amber-700 font-medium mt-1">
                                        * Anda tidak dapat menurunkan peran (role) akun yang sedang aktif digunakan.
                                    </p>
                                </template>
                            </div>

                            <div class="pt-3 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    @click="editUserModalOpen = false"
                                    class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs transition-colors"
                                >
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
