@extends('layouts.dashboard')

@section('title', 'Katalog Layanan & Tarif')

@section('content')
<div
    x-data="{
        addServiceModalOpen: false,
        editServiceModalOpen: false,
        selectedService: null,
        openEdit(service) {
            this.selectedService = service;
            this.editServiceModalOpen = true;
        }
    }"
    class="space-y-6"
>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Katalog Layanan & Tarif Outlet
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Kelola paket pencucian koin mandiri, layanan drop-off titip cuci, dan tarif mesin komersial
            </p>
        </div>

        @can('create', App\Models\Service::class)
            <button
                type="button"
                @click="addServiceModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all self-start sm:self-auto"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Layanan Baru</span>
            </button>
        @endcan
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Layanan"
            :value="$stats['total'] ?? 0"
            hint="Paket cuci terdaftar"
            color="slate"
        />
        <x-stat-card
            title="Tersedia Aktif"
            :value="$stats['tersedia'] ?? 0"
            hint="Dapat dipesan pelanggan"
            color="emerald"
        />
        <x-stat-card
            title="Mesin Penuh / Sibuk"
            :value="$stats['sibuk'] ?? 0"
            hint="Status antrean padat"
            color="amber"
        />
        <x-stat-card
            title="Tidak Tersedia / Arsip"
            :value="$stats['nonaktif'] ?? 0"
            hint="Layanan dinonaktifkan"
            color="rose"
        />
    </div>

    <!-- Toolbar Pencarian & Filter Kategori -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('admin.services.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
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
                    placeholder="Cari nama layanan atau deskripsi..."
                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                />
            </div>

            <div class="sm:col-span-3">
                <select
                    name="category"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                >
                    <option value="">Semua Kategori</option>
                    <option value="Self Service" {{ ($category ?? '') === 'Self Service' ? 'selected' : '' }}>Self Service (Koin Mandiri)</option>
                    <option value="Non-Self Service" {{ ($category ?? '') === 'Non-Self Service' ? 'selected' : '' }}>Non-Self Service (Titip Cuci)</option>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors"
                >
                    Filter
                </button>
                @if (($search ?? '') || ($category ?? ''))
                    <a
                        href="{{ route('admin.services.index') }}"
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

    <!-- Tabel Daftar Layanan -->
    <x-card padding="p-0">
        @if ($services->isNotEmpty())
            <x-table :headers="['Nama Layanan', 'Kategori & Estimasi', 'Unit / Kapasitas', 'Tarif Layanan', 'Status Operasional', 'Total Order', 'Aksi']">
                @foreach ($services as $serviceItem)
                    @php
                        $isArchived = str_contains(strtolower($serviceItem->name), 'kilo') || str_contains(strtolower($serviceItem->name), 'setrika') || $serviceItem->status === 'tidak_tersedia';
                        $hasOrders = ($serviceItem->orders_count ?? 0) > 0;
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-xs">{{ $serviceItem->name }}</span>
                                @if ($isArchived)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-slate-200 text-slate-700">
                                        Arsip
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 font-mono mt-0.5">slug: {{ $serviceItem->slug }}</div>
                        </td>

                        <td class="px-5 py-4 text-xs text-slate-700">
                            <div class="font-semibold text-slate-900">{{ $serviceItem->category }}</div>
                            <div class="text-[11px] text-slate-500">Estimasi: {{ $serviceItem->estimate }}</div>
                        </td>

                        <td class="px-5 py-4 text-xs text-slate-700">
                            <span class="font-semibold text-slate-900">{{ $serviceItem->unit ?? 'Mesin' }}</span>
                        </td>

                        <td class="px-5 py-4 text-xs font-extrabold text-slate-900">
                            Rp {{ number_format($serviceItem->price, 0, ',', '.') }}
                        </td>

                        <td class="px-5 py-4">
                            @if ($serviceItem->status === 'tersedia')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Tersedia</span>
                                </span>
                            @elseif ($serviceItem->status === 'sibuk')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>Penuh / Sibuk</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-4 text-xs font-bold text-slate-800">
                            {{ number_format($serviceItem->orders_count ?? 0, 0, ',', '.') }} pesanan
                        </td>

                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                @can('update', $serviceItem)
                                    <button
                                        type="button"
                                        @click="openEdit({{ Js::from([
                                            'id' => $serviceItem->id,
                                            'name' => $serviceItem->name,
                                            'category' => $serviceItem->category,
                                            'price' => $serviceItem->price,
                                            'estimate' => $serviceItem->estimate,
                                            'unit' => $serviceItem->unit ?? 'Mesin',
                                            'status' => $serviceItem->status,
                                            'description' => $serviceItem->description ?? '',
                                            'tag' => $serviceItem->tag ?? '',
                                            'features' => implode(', ', $serviceItem->features ?? []),
                                            'image_url' => $serviceItem->image ? Storage::url($serviceItem->image) : null,
                                        ]) }})"
                                        class="px-2.5 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs transition-colors"
                                    >
                                        Edit
                                    </button>
                                @endcan

                                @can('delete', $serviceItem)
                                    <form
                                        action="{{ route('admin.services.destroy', $serviceItem) }}"
                                        method="POST"
                                        class="inline-block"
                                        onsubmit="return confirm('Hapus permanen layanan {{ $serviceItem->name }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            @if ($hasOrders) disabled title="Layanan sudah memiliki order dan tidak dapat dihapus, hanya dapat dinonaktifkan." @endif
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors
                                                {{ $hasOrders ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-rose-50 hover:bg-rose-100 text-rose-700' }}
                                            "
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="p-4 border-t border-slate-200/80">
                {{ $services->links() }}
            </div>
        @else
            <x-empty-state
                title="Tidak Ada Layanan Ditemukan"
                description="Tidak ada paket layanan yang sesuai dengan pencarian atau filter kategori yang dipilih."
            />
        @endif
    </x-card>

    <!-- Modal Tambah Layanan Baru -->
    <div
        x-show="addServiceModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="addServiceModalOpen"
                @click="addServiceModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="addServiceModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <div class="px-6 py-5 bg-gradient-to-r from-sky-900 to-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold">Tambah Layanan Baru</h3>
                        <p class="text-xs text-sky-200">Buat paket cuci baru untuk katalog outlet</p>
                    </div>
                    <button
                        type="button"
                        @click="addServiceModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form action="{{ route('admin.services.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label for="srv-name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nama Layanan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="srv-name"
                            type="text"
                            name="name"
                            required
                            placeholder="Contoh: Cuci Koin Bedcover 14kg"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="srv-category" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Kategori <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="srv-category"
                                name="category"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            >
                                <option value="Self Service">Self Service</option>
                                <option value="Non-Self Service">Non-Self Service</option>
                            </select>
                        </div>

                        <div>
                            <label for="srv-unit" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Satuan / Unit <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="srv-unit"
                                type="text"
                                name="unit"
                                value="Mesin"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="srv-price" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Tarif Harga (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="srv-price"
                                type="number"
                                name="price"
                                required
                                min="0"
                                step="1000"
                                placeholder="15000"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                        </div>

                        <div>
                            <label for="srv-estimate" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Durasi Estimasi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="srv-estimate"
                                type="text"
                                name="estimate"
                                required
                                placeholder="Contoh: 45 Menit / 1 Hari"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="srv-status" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Status Operasional <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="srv-status"
                            name="status"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        >
                            <option value="tersedia">Tersedia (Siap Dipesan)</option>
                            <option value="sibuk">Penuh / Antrean Padat</option>
                            <option value="tidak_tersedia">Tidak Tersedia / Nonaktif</option>
                        </select>
                    </div>

                    <div>
                        <label for="srv-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Deskripsi Layanan (Opsional)
                        </label>
                        <textarea
                            id="srv-desc"
                            name="description"
                            rows="2"
                            placeholder="Keterangan kapasitas beban pakaian atau deterjen..."
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="srv-tag" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tag (Opsional)</label>
                            <input id="srv-tag" type="text" name="tag" maxlength="50" placeholder="Contoh: Populer" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500" />
                        </div>
                        <div>
                            <label for="srv-image" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Gambar (Opsional)</label>
                            <input id="srv-image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-sky-50 file:px-2.5 file:py-1.5 file:text-xs file:font-bold file:text-sky-700" />
                        </div>
                    </div>

                    <div>
                        <label for="srv-features" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Fitur (Opsional, pisahkan dengan koma)</label>
                        <input id="srv-features" type="text" name="features" placeholder="Contoh: Deterjen gratis, Pewangi, Kapasitas 10kg" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500" />
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="addServiceModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs transition-colors"
                        >
                            Simpan Layanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Layanan -->
    <div
        x-show="editServiceModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="editServiceModalOpen"
                @click="editServiceModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="editServiceModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <template x-if="selectedService">
                    <div>
                        <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-sky-900 text-white flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold">Edit Layanan Laundry</h3>
                                <p class="text-xs text-sky-200" x-text="selectedService.name"></p>
                            </div>
                            <button
                                type="button"
                                @click="editServiceModalOpen = false"
                                class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form :action="'/admin/services/' + selectedService.id" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="edit-srv-name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Nama Layanan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="edit-srv-name"
                                    type="text"
                                    name="name"
                                    :value="selectedService.name"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit-srv-category" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                        Kategori <span class="text-rose-500">*</span>
                                    </label>
                                    <select
                                        id="edit-srv-category"
                                        name="category"
                                        required
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    >
                                        <option value="Self Service" :selected="selectedService.category === 'Self Service'">Self Service</option>
                                        <option value="Non-Self Service" :selected="selectedService.category === 'Non-Self Service'">Non-Self Service</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="edit-srv-unit" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                        Satuan / Unit <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="edit-srv-unit"
                                        type="text"
                                        name="unit"
                                        :value="selectedService.unit"
                                        required
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit-srv-price" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                        Tarif Harga (Rp) <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="edit-srv-price"
                                        type="number"
                                        name="price"
                                        :value="selectedService.price"
                                        required
                                        min="0"
                                        step="1000"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    />
                                </div>

                                <div>
                                    <label for="edit-srv-estimate" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                        Durasi Estimasi <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="edit-srv-estimate"
                                        type="text"
                                        name="estimate"
                                        :value="selectedService.estimate"
                                        required
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="edit-srv-status" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Status Operasional <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="edit-srv-status"
                                    name="status"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                >
                                    <option value="tersedia" :selected="selectedService.status === 'tersedia'">Tersedia (Siap Dipesan)</option>
                                    <option value="sibuk" :selected="selectedService.status === 'sibuk'">Penuh / Antrean Padat</option>
                                    <option value="tidak_tersedia" :selected="selectedService.status === 'tidak_tersedia'">Tidak Tersedia / Nonaktif</option>
                                </select>
                            </div>

                            <div>
                                <label for="edit-srv-desc" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                    Deskripsi Layanan (Opsional)
                                </label>
                                <textarea
                                    id="edit-srv-desc"
                                    name="description"
                                    rows="2"
                                    x-text="selectedService.description"
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit-srv-tag" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tag (Opsional)</label>
                                    <input id="edit-srv-tag" type="text" name="tag" maxlength="50" :value="selectedService.tag" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500" />
                                </div>
                                <div>
                                    <label for="edit-srv-image" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Ganti Gambar (Opsional)</label>
                                    <input id="edit-srv-image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-sky-50 file:px-2.5 file:py-1.5 file:text-xs file:font-bold file:text-sky-700" />
                                </div>
                            </div>

                            <template x-if="selectedService.image_url">
                                <div class="flex items-center gap-3">
                                    <img :src="selectedService.image_url" alt="Gambar layanan" class="h-12 w-12 rounded-lg object-cover border border-slate-200" />
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300" />
                                        Hapus gambar saat ini
                                    </label>
                                </div>
                            </template>

                            <div>
                                <label for="edit-srv-features" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Fitur (Opsional, pisahkan dengan koma)</label>
                                <input id="edit-srv-features" type="text" name="features" :value="selectedService.features" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500" />
                            </div>

                            <div class="pt-3 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    @click="editServiceModalOpen = false"
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
