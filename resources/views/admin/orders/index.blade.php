@extends('layouts.dashboard')

@section('title', 'Manajemen Seluruh Pesanan')

@section('content')
<div
    x-data="{
        createModalOpen: false,
        manageModalOpen: false,
        selectedOrder: null,
        customerType: 'registered',
        userId: '',
        customerName: '',
        customerPhone: '',
        serviceId: '{{ $services->first()?->id ?? '' }}',
        qty: 1,
        services: {{ Js::from($services) }},
        get selectedService() {
            return this.services.find(s => s.id == this.serviceId) || null;
        },
        get estimatedTotal() {
            return (this.selectedService ? Number(this.selectedService.price) : 0) * Number(this.qty || 1);
        },
        openManage(order) {
            this.selectedOrder = order;
            this.manageModalOpen = true;
        }
    }"
    class="space-y-6"
>

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                Manajemen Seluruh Pesanan Laundry
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Pantau seluruh siklus order, filter status pembayaran & tanggal, serta kelola antrean mesin
            </p>
        </div>

        @can('create', App\Models\Order::class)
            <button
                type="button"
                @click="createModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all self-start sm:self-auto"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Buat Pesanan Baru</span>
            </button>
        @endcan
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Pesanan"
            :value="$stats['total'] ?? 0"
            hint="Seluruh riwayat cucian"
            color="slate"
        />
        <x-stat-card
            title="Menunggu (Pending)"
            :value="$stats['pending'] ?? 0"
            hint="Belum masuk mesin"
            color="amber"
        />
        <x-stat-card
            title="Sedang Diproses"
            :value="$stats['diproses'] ?? 0"
            hint="Cuci, kering, atau lipat"
            color="sky"
        />
        <x-stat-card
            title="Siap Diambil & Selesai"
            :value="($stats['siap_diambil'] ?? 0) + ($stats['selesai'] ?? 0)"
            hint="Tuntas dikerjakan"
            color="emerald"
        />
    </div>

    <!-- Toolbar Pencarian & Filter Kompleks -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <!-- Search Input -->
            <div class="sm:col-span-4 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Kode order, nama, atau no HP..."
                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                />
            </div>

            <!-- Status Cucian Filter -->
            <div class="sm:col-span-3">
                <select
                    name="status"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                >
                    <option value="">Semua Status Cucian</option>
                    <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                    <option value="diproses" {{ ($status ?? '') === 'diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="siap_diambil" {{ ($status ?? '') === 'siap_diambil' ? 'selected' : '' }}>Siap Diambil</option>
                    <option value="selesai" {{ ($status ?? '') === 'selesai' ? 'selected' : '' }}>Selesai (Sudah Diambil)</option>
                </select>
            </div>

            <!-- Status Pembayaran Filter -->
            <div class="sm:col-span-2">
                <select
                    name="payment_status"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                >
                    <option value="">Status Bayar</option>
                    <option value="paid" {{ ($paymentStatus ?? '') === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
                    <option value="pending" {{ ($paymentStatus ?? '') === 'pending' ? 'selected' : '' }}>Belum Lunas</option>
                </select>
            </div>

            <!-- Tanggal Filter -->
            <div class="sm:col-span-2">
                <input
                    type="date"
                    name="date"
                    value="{{ $date ?? '' }}"
                    class="w-full py-2 px-2.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                />
            </div>

            <!-- Submit Button & Reset -->
            <div class="sm:col-span-1 flex items-center gap-1.5">
                <button
                    type="submit"
                    class="w-full py-2 px-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors text-center"
                >
                    Cari
                </button>
                @if (($search ?? '') || ($status ?? '') || ($paymentStatus ?? '') || ($date ?? ''))
                    <a
                        href="{{ route('admin.orders.index') }}"
                        class="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition-colors shrink-0"
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

    <!-- Tabel Seluruh Pesanan -->
    <x-card padding="p-0">
        @if ($orders->isNotEmpty())
            <x-table :headers="['Kode Order', 'Waktu', 'Pelanggan', 'Layanan', 'Mesin', 'Total Biaya', 'Status Cucian', 'Pembayaran', 'Aksi']">
                @foreach ($orders as $order)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-slate-900 text-xs">
                            {{ $order->order_code }}
                            @if ($order->checkout)
                                <span class="block mt-0.5 text-[10px] font-semibold text-sky-700" title="Dibayar sekaligus dalam 1 transaksi online">{{ $order->checkout->code }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-500">
                            {{ $order->created_at->format('d M, H:i') }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-bold text-slate-900 text-xs">
                                {{ $order->customer_name ?? $order->user?->name ?? 'Walk-in' }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ $order->customer_phone ?? $order->user?->phone ?? '-' }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-800 text-xs">{{ $order->service?->name ?? 'Layanan' }}</div>
                            <div class="text-[11px] text-slate-500">{{ $order->service?->category ?? 'Laundromat' }}</div>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-700">
                            @if (($order->service?->unit ?? 'Mesin') === 'Mesin')
                                <span class="font-bold text-slate-900">{{ $order->machine_qty }}</span> × Mesin
                            @else
                                <span class="font-bold text-slate-900">{{ $order->machine_qty }}</span> Paket
                            @endif
                        </td>
                        <td class="px-5 py-4 text-xs font-extrabold text-slate-900">
                            Rp {{ number_format($order->total_price, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-4">
                            <x-badge :status="$order->status" size="sm" />
                        </td>
                        <td class="px-5 py-4">
                            @php
                                $payment = $order->paymentForDisplay();
                                $payStatus = $payment?->displayStatus() ?? 'pending';
                            @endphp
                            <x-badge :payment="$payStatus" size="sm" />
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="openManage({{ Js::from([
                                        'id' => $order->id,
                                        'order_code' => $order->order_code,
                                        'status' => $order->status,
                                        'machine_qty' => $order->machine_qty,
                                        'unit_price' => $order->unit_price,
                                        'total_price' => $order->total_price,
                                        'customer_name' => $order->customer_name ?? $order->user?->name ?? 'Walk-in',
                                        'customer_phone' => $order->customer_phone ?? $order->user?->phone ?? '-',
                                        'service_name' => $order->service?->name ?? 'Layanan',
                                        'unit' => $order->service?->unit ?? 'Mesin',
                                        'payment_status' => $payStatus,
                                        'payment_method' => $payment?->method ?? 'cash',
                                        'can_edit_qty' => in_array($order->status, ['pending', 'diproses']),
                                    ]) }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs transition-colors"
                                >
                                    Kelola
                                </button>

                                @can('delete', $order)
                                    <form
                                        action="{{ route('admin.orders.destroy', $order) }}"
                                        method="POST"
                                        class="inline-block"
                                        onsubmit="return confirm('Hapus permanen pesanan #{{ $order->order_code }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs transition-colors"
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
                {{ $orders->links() }}
            </div>
        @else
            <x-empty-state
                title="Tidak Ada Pesanan Ditemukan"
                description="Tidak ada pesanan laundry yang cocok dengan kriteria pencarian atau filter yang ditentukan."
            />
        @endif
    </x-card>

    <!-- Modal Form Tambah Pesanan Baru -->
    <div
        x-show="createModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="createModalOpen"
                @click="createModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="createModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-sky-900 to-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold">Buat Pesanan Laundry Baru</h3>
                        <p class="text-xs text-sky-200">Input transaksi laundry kasir atau atas nama pelanggan</p>
                    </div>
                    <button
                        type="button"
                        @click="createModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form action="{{ route('admin.orders.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Data Pemesan <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <button
                                type="button"
                                @click="customerType = 'registered'"
                                :class="customerType === 'registered' ? 'bg-sky-600 text-white border-sky-600' : 'bg-slate-50 text-slate-700 border-slate-300'"
                                class="py-2 px-3 rounded-xl border text-xs font-bold transition-colors text-center"
                            >
                                Member Terdaftar
                            </button>
                            <button
                                type="button"
                                @click="customerType = 'walkin'"
                                :class="customerType === 'walkin' ? 'bg-sky-600 text-white border-sky-600' : 'bg-slate-50 text-slate-700 border-slate-300'"
                                class="py-2 px-3 rounded-xl border text-xs font-bold transition-colors text-center"
                            >
                                Walk-in (Manual)
                            </button>
                        </div>

                        <div x-show="customerType === 'registered'">
                            <select
                                name="user_id"
                                :required="customerType === 'registered'"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            >
                                <option value="">-- Pilih Akun Pelanggan --</option>
                                @foreach ($customers as $cust)
                                    <option value="{{ $cust->id }}">
                                        {{ $cust->name }} ({{ $cust->phone ?: 'No telp -' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="customerType === 'walkin'" class="space-y-3">
                            <input
                                type="text"
                                name="customer_name"
                                placeholder="Nama Pelanggan Walk-in *"
                                :required="customerType === 'walkin'"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                            <input
                                type="tel"
                                name="customer_phone"
                                placeholder="Nomor Telepon / WhatsApp (08xxxx) *"
                                :required="customerType === 'walkin'"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="adm-service" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Pilih Layanan <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="adm-service"
                            name="service_id"
                            x-model="serviceId"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                        >
                            @foreach ($services as $srv)
                                <option value="{{ $srv->id }}">
                                    {{ $srv->name }} (Rp {{ number_format($srv->price, 0, ',', '.') }} / {{ $srv->unit ?? 'Mesin' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="adm-qty" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Jumlah Unit Mesin / Paket <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="if (qty > 1) qty--"
                                class="w-10 h-10 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center transition-colors"
                            >-</button>
                            <input
                                id="adm-qty"
                                type="number"
                                name="machine_qty"
                                x-model.number="qty"
                                min="1"
                                max="20"
                                required
                                class="w-20 text-center rounded-xl border border-slate-300 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                            />
                            <button
                                type="button"
                                @click="if (qty < 20) qty++"
                                class="w-10 h-10 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center transition-colors"
                            >+</button>
                            <span class="text-xs text-slate-500">Unit mesin</span>
                        </div>
                    </div>

                    <div class="rounded-xl p-4 bg-sky-50/70 border border-sky-200/80 space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-700">
                            <span>Tarif Satuan Layanan:</span>
                            <span class="font-semibold text-slate-900" x-text="selectedService ? 'Rp ' + Number(selectedService.price).toLocaleString('id-ID') : '-'"></span>
                        </div>
                        <div class="pt-2 border-t border-sky-200 flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Total Biaya (Estimasi):</span>
                            <span class="text-sm font-extrabold text-sky-700" x-text="'Rp ' + estimatedTotal.toLocaleString('id-ID') + ' (estimasi)'"></span>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="createModalOpen = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-colors"
                        >
                            Simpan Pesanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Detail & Aksi Order Admin -->
    <div
        x-show="manageModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="manageModalOpen"
                @click="manageModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="manageModalOpen"
                class="relative z-10 inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200"
            >
                <template x-if="selectedOrder">
                    <div>
                        <!-- Header Modal -->
                        <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-sky-900 text-white flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-base font-extrabold text-white" x-text="'#' + selectedOrder.order_code"></span>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-white/20 text-white" x-text="selectedOrder.status"></span>
                                </div>
                                <p class="text-xs text-sky-200 mt-0.5" x-text="selectedOrder.customer_name + ' • ' + selectedOrder.service_name"></p>
                            </div>
                            <button
                                type="button"
                                @click="manageModalOpen = false"
                                class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-6">

                            <!-- Section 1: Ubah Status Order -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    1. Ubah Status Pesanan
                                </h4>
                                <form :action="'/admin/orders/' + selectedOrder.id" method="POST" class="flex items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <select
                                        name="status"
                                        class="flex-1 rounded-xl border border-slate-300 py-2 px-3 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                    >
                                        <option value="pending" :selected="selectedOrder.status === 'pending'">Menunggu (Pending)</option>
                                        <option value="diproses" :selected="selectedOrder.status === 'diproses'">Diproses (Washing/Drying)</option>
                                        <option value="siap_diambil" :selected="selectedOrder.status === 'siap_diambil'">Siap Diambil</option>
                                        <option value="selesai" :selected="selectedOrder.status === 'selesai'">Selesai (Sudah Diambil)</option>
                                    </select>
                                    <button
                                        type="submit"
                                        class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs transition-colors shrink-0"
                                    >
                                        Simpan Status
                                    </button>
                                </form>
                            </div>

                            <!-- Section 2: Ubah Machine Qty (Hanya Pending / Diproses) -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                        2. Ubah Jumlah Mesin
                                    </h4>
                                    <span class="text-[11px] font-semibold text-slate-500" x-text="'Saat ini: ' + selectedOrder.machine_qty + ' unit'"></span>
                                </div>

                                <template x-if="selectedOrder.can_edit_qty">
                                    <form :action="'/admin/orders/' + selectedOrder.id" method="POST" class="flex items-center gap-3">
                                        @csrf
                                        @method('PUT')
                                        <input
                                            type="number"
                                            name="machine_qty"
                                            :value="selectedOrder.machine_qty"
                                            min="1"
                                            max="20"
                                            required
                                            class="w-24 rounded-xl border border-slate-300 py-2 text-center text-xs font-bold text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                        />
                                        <span class="text-xs text-slate-500">Unit Mesin</span>
                                        <button
                                            type="submit"
                                            class="ml-auto px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-colors shrink-0"
                                        >
                                            Hitung Ulang & Simpan
                                        </button>
                                    </form>
                                </template>

                                <template x-if="!selectedOrder.can_edit_qty">
                                    <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Jumlah mesin dikunci karena pesanan sudah berstatus <strong>siap diambil</strong> atau <strong>selesai</strong>.</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Section 3: Konfirmasi Pembayaran Kasir -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                        3. Pelunasan Pembayaran
                                    </h4>
                                    <span class="text-xs font-extrabold text-slate-900" x-text="'Total: Rp ' + Number(selectedOrder.total_price).toLocaleString('id-ID')"></span>
                                </div>

                                <template x-if="selectedOrder.payment_status === 'paid'">
                                    <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Pembayaran pesanan ini sudah LUNAS (<span x-text="selectedOrder.payment_method.toUpperCase()"></span>)</span>
                                    </div>
                                </template>

                                <template x-if="selectedOrder.payment_status !== 'paid'">
                                    <form :action="'/admin/orders/' + selectedOrder.id + '/payment'" method="POST" class="flex items-center gap-3">
                                        @csrf
                                        <select
                                            name="method"
                                            class="flex-1 rounded-xl border border-slate-300 py-2 px-3 text-xs text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-500"
                                        >
                                            <option value="cash">Tunai (Cash)</option>
                                            <option value="transfer">Transfer Bank</option>
                                            <option value="e-wallet">QRIS / E-Wallet</option>
                                        </select>
                                        <button
                                            type="submit"
                                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors shrink-0"
                                        >
                                            Konfirmasi Lunas
                                        </button>
                                    </form>
                                </template>
                            </div>

                        </div>

                        <!-- Footer Modal -->
                        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                            <button
                                type="button"
                                @click="manageModalOpen = false"
                                class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
