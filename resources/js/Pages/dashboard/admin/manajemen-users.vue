<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import DashboardLayout from '@/Layouts/dashboard.vue';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, Title, Tooltip, Legend,
    LineElement, LinearScale, PointElement, CategoryScale,
    Filler, ArcElement, BarElement
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, LinearScale,
    PointElement, CategoryScale, Filler, ArcElement, BarElement);

// ── Props dari Inertia ───────────────────────────────────────────────
const props = defineProps({
    users:     Object,   // paginated
    stats:     Object,
    filters:   Object,
    chartData: Object,
});

const authUser = computed(() => usePage().props.auth.user);
const flash    = computed(() => usePage().props.flash ?? {});

// ── Search / Filter ──────────────────────────────────────────────────
const search    = ref(props.filters?.search ?? '');
const roleFilter = ref(props.filters?.role ?? '');

let searchTimeout = null;
watch([search, roleFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.users'), {
            search: search.value,
            role: roleFilter.value,
        }, { preserveState: true, replace: true });
    }, 350);
});

// ── Collapsible chart ────────────────────────────────────────────────
const isChartExpanded = ref(true);
onMounted(() => {
    const saved = localStorage.getItem('manajemen-users-chart-expanded');
    if (saved !== null) isChartExpanded.value = saved === 'true';
});
watch(isChartExpanded, v => localStorage.setItem('manajemen-users-chart-expanded', v));

// ── Modals ───────────────────────────────────────────────────────────
const showFormModal   = ref(false);
const showDeleteModal = ref(false);
const editingUser     = ref(null);
const deletingUser    = ref(null);

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    showFormModal.value = true;
}

function openEdit(u) {
    editingUser.value = u;
    form.name    = u.name;
    form.email   = u.email;
    form.phone   = u.phone ?? '';
    form.address = u.address ?? '';
    form.role    = u.role;
    form.password = '';
    form.clearErrors();
    showFormModal.value = true;
}

function openDelete(u) {
    deletingUser.value = u;
    showDeleteModal.value = true;
}

function closeModals() {
    showFormModal.value   = false;
    showDeleteModal.value = false;
    editingUser.value     = null;
    deletingUser.value    = null;
}

// ── Inertia Form ─────────────────────────────────────────────────────
const form = useForm({
    name: '', email: '', password: '', phone: '', address: '', role: 'pelanggan',
});

function submitForm() {
    if (editingUser.value) {
        form.put(route('admin.users.update', editingUser.value.id), {
            onSuccess: closeModals,
        });
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: closeModals,
        });
    }
}

function confirmDelete() {
    router.delete(route('admin.users.destroy', deletingUser.value.id), {
        onSuccess: closeModals,
    });
}

// ── Helpers ──────────────────────────────────────────────────────────
const getRoleClass = (role) => {
    const map = {
        admin:     'bg-rose-100 text-rose-700 border-rose-200',
        operator:  'bg-emerald-100 text-emerald-700 border-emerald-200',
        kurir:     'bg-amber-100 text-amber-700 border-amber-200',
        pelanggan: 'bg-slate-100 text-slate-700 border-slate-200',
    };
    return map[role] ?? 'bg-slate-100 text-slate-700 border-slate-200';
};

// ── Stat cards config ────────────────────────────────────────────────
const statCards = computed(() => [
    { label: 'Total Pengguna',   value: props.stats?.total ?? 0,     icon: 'fa-users',        color: 'text-blue-600',   bg: 'bg-blue-500/10' },
    { label: 'Administrator',    value: props.stats?.admin ?? 0,     icon: 'fa-user-shield',  color: 'text-rose-600',   bg: 'bg-rose-500/10' },
    { label: 'Operator Toko',    value: props.stats?.operator ?? 0,  icon: 'fa-user-gear',    color: 'text-emerald-600',bg: 'bg-emerald-500/10' },
    { label: 'Kurir Lapangan',   value: props.stats?.kurir ?? 0,     icon: 'fa-motorcycle',   color: 'text-amber-600',  bg: 'bg-amber-500/10' },
]);

// ── Chart Configs ────────────────────────────────────────────────────
const tooltipDefaults = {
    backgroundColor: '#0A0A0B',
    titleFont: { family: 'Space Mono', size: 10 },
    bodyFont: { family: 'Inter', size: 12, weight: 'bold' },
    padding: 12, cornerRadius: 4,
};

const chartDistributionData = computed(() => ({
    labels: ['Admin', 'Operator', 'Kurir', 'Pelanggan'],
    datasets: [{ data: props.chartData?.distribution ?? [0,0,0,0], backgroundColor: ['#e11d48','#059669','#d97706','#475569'], borderWidth: 0, hoverOffset: 4 }],
}));

const doughnutOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'right', labels: { font: { family: 'Inter', size: 10, weight: 'bold' }, color: '#888' } }, tooltip: { ...tooltipDefaults, displayColors: true } },
    cutout: '70%',
};

const chartGrowthData = computed(() => ({
    labels: (props.chartData?.weeklyGrowth ?? []).map(w => w.label),
    datasets: [{ label: 'User Baru', data: (props.chartData?.weeklyGrowth ?? []).map(w => w.value), borderColor: '#5B4CF3', backgroundColor: 'rgba(91,76,243,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#fff', pointBorderColor: '#5B4CF3', pointBorderWidth: 2, pointHoverRadius: 6 }],
}));

const lineOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults, displayColors: false } },
    scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } }, x: { grid: { display: false }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } } },
};

const chartVerificationData = computed(() => ({
    labels: ['Terverifikasi', 'Belum Verifikasi'],
    datasets: [{ data: props.chartData?.verified ?? [0,0], backgroundColor: ['#059669','#cbd5e1'], borderWidth: 0, hoverOffset: 4 }],
}));

const chartGrowthPerRoleData = computed(() => ({
    labels: (props.chartData?.growthPerRole ?? []).map(m => m.label),
    datasets: [
        { label: 'Admin',    data: (props.chartData?.growthPerRole ?? []).map(m => m.admin),    backgroundColor: '#e11d48', borderRadius: 4 },
        { label: 'Operator', data: (props.chartData?.growthPerRole ?? []).map(m => m.operator), backgroundColor: '#059669', borderRadius: 4 },
        { label: 'Kurir',    data: (props.chartData?.growthPerRole ?? []).map(m => m.kurir),    backgroundColor: '#d97706', borderRadius: 4 },
    ],
}));

const barOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 10, weight: 'bold' }, color: '#888' } }, tooltip: { ...tooltipDefaults } },
    scales: {
        x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } },
        y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } },
    },
};
</script>

<template>
    <Head title="Manajemen User - Hi Wash" />

    <DashboardLayout title="Manajemen User">
        <div class="space-y-10 pb-20">

            <!-- Flash messages -->
            <div v-if="flash.success" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-sm px-5 py-3 text-sm font-bold">
                <i class="fa-solid fa-circle-check"></i> {{ flash.success }}
            </div>
            <div v-if="flash.error" class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-sm px-5 py-3 text-sm font-bold">
                <i class="fa-solid fa-circle-xmark"></i> {{ flash.error }}
            </div>

            <!-- Header -->
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <h1 class="text-4xl font-black tracking-tighter text-text uppercase">
                        Manajemen <span class="text-muted font-medium">User</span>
                    </h1>
                    <p class="text-muted font-medium italic">Kelola hak akses dan informasi personil HiWash Laundry.</p>
                </div>
                <button @click="openCreate"
                    class="bg-primary hover:bg-primary-hover text-white px-8 py-4 rounded-sm font-black uppercase tracking-widest text-xs shadow-lg transition-all transform hover:-translate-y-1 flex items-center gap-3">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Pengguna Baru
                </button>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="(stat, index) in statCards" :key="index"
                    class="bg-surface border border-border p-6 rounded-sm shadow-sm relative group hover:border-primary/50 transition-all overflow-hidden">
                    <div class="absolute -right-2 -bottom-2 opacity-5 group-hover:opacity-10 transition-opacity">
                        <i :class="['fa-solid', stat.icon]" class="text-6xl"></i>
                    </div>
                    <div class="flex items-center gap-4 mb-4">
                        <div :class="[stat.bg, stat.color]" class="w-10 h-10 rounded-full flex items-center justify-center text-sm">
                            <i :class="['fa-solid', stat.icon]"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-muted">{{ stat.label }}</span>
                    </div>
                    <h3 class="text-3xl font-black text-text tracking-tighter">{{ stat.value.toLocaleString('id-ID') }}</h3>
                </div>
            </div>

            <!-- Chart Section (Collapsible) -->
            <div class="bg-surface border border-border rounded-sm shadow-sm overflow-hidden">
                <button @click="isChartExpanded = !isChartExpanded"
                    class="w-full flex items-center justify-between px-6 py-4 hover:bg-container/50 transition-colors group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-text">Visual Analitik - Pertumbuhan User</h3>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[9px] font-mono text-muted uppercase tracking-widest italic">Live Trend Analysis</span>
                        </div>
                        <i :class="['fa-solid transition-transform duration-300', isChartExpanded ? 'fa-chevron-up' : 'fa-chevron-down text-primary']"></i>
                    </div>
                </button>

                <transition
                    enter-active-class="transition-all duration-500 ease-out"
                    leave-active-class="transition-all duration-300 ease-in"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[2000px] opacity-100"
                    leave-from-class="max-h-[2000px] opacity-100"
                    leave-to-class="max-h-0 opacity-0">
                    <div v-show="isChartExpanded" class="p-6 border-t border-dashed border-border overflow-hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-12">
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Distribusi Role</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Doughnut :data="chartDistributionData" :options="doughnutOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Trend Registrasi Global (8 Minggu)</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Line :data="chartGrowthData" :options="lineOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Status Verifikasi Email</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Doughnut :data="chartVerificationData" :options="doughnutOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Registrasi per Role (4 Bulan)</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Bar :data="chartGrowthPerRoleData" :options="barOptions" />
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Table Section -->
            <div class="space-y-4">
                <div class="flex flex-col md:flex-row gap-4 justify-between items-end">
                    <!-- Search -->
                    <div class="w-full md:max-w-md relative group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-primary transition-colors"></i>
                        <input v-model="search" type="text" placeholder="Cari nama atau email..."
                            class="w-full pl-12 pr-4 py-3 bg-white border border-border rounded-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-sm" />
                    </div>

                    <div class="flex gap-2">
                        <!-- Role filter -->
                        <select v-model="roleFilter"
                            class="px-4 py-3 border border-border bg-surface text-text rounded-sm text-xs font-bold uppercase tracking-widest outline-none focus:border-primary transition">
                            <option value="">Semua Role</option>
                            <option value="admin">Admin</option>
                            <option value="operator">Operator</option>
                            <option value="kurir">Kurir</option>
                            <option value="pelanggan">Pelanggan</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-surface border border-border rounded-sm shadow-xl overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-container/50 border-b border-border">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Profil Pengguna</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Role</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Verifikasi</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Tanggal Gabung</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-muted italic text-sm">
                                    <i class="fa-solid fa-inbox text-2xl mb-2 block opacity-40"></i>
                                    Tidak ada pengguna ditemukan.
                                </td>
                            </tr>
                            <tr v-for="u in users.data" :key="u.id" class="hover:bg-container/30 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold border border-primary/20 shrink-0 uppercase">
                                            {{ u.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-text group-hover:text-primary transition-colors">{{ u.name }}</p>
                                            <p class="text-xs text-muted font-medium">{{ u.email }}</p>
                                            <p v-if="u.phone" class="text-xs text-muted/70">{{ u.phone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getRoleClass(u.role)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border">
                                        {{ u.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div :class="u.verified ? 'bg-emerald-500' : 'bg-slate-300'" class="w-2 h-2 rounded-full"></div>
                                        <span class="text-xs font-bold uppercase tracking-tighter text-text">
                                            {{ u.verified ? 'Terverifikasi' : 'Belum' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-muted">{{ u.joined }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openEdit(u)"
                                            class="w-8 h-8 flex items-center justify-center rounded-sm border border-border text-muted hover:bg-text hover:text-white transition-all">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button @click="openDelete(u)"
                                            :disabled="u.id === authUser.id"
                                            class="w-8 h-8 flex items-center justify-center rounded-sm border border-border text-muted hover:bg-rose-600 hover:border-rose-600 hover:text-white transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-between px-2 pt-4">
                    <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                        Menampilkan {{ users.from ?? 0 }}–{{ users.to ?? 0 }} dari {{ users.total }} pengguna
                    </p>
                    <div class="flex gap-2">
                        <template v-for="link in users.links" :key="link.label">
                            <button
                                v-if="link.label === '&laquo; Previous'"
                                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                                :disabled="!link.url"
                                class="w-8 h-8 flex items-center justify-center border border-border text-muted rounded-sm disabled:opacity-40 disabled:cursor-not-allowed hover:bg-container transition">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </button>
                            <button
                                v-else-if="link.label === 'Next &raquo;'"
                                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                                :disabled="!link.url"
                                class="w-8 h-8 flex items-center justify-center border border-border text-muted rounded-sm disabled:opacity-40 disabled:cursor-not-allowed hover:bg-container transition">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </button>
                            <button v-else
                                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                                :class="link.active ? 'bg-primary text-white border-primary' : 'border-border text-text hover:bg-container'"
                                class="w-8 h-8 flex items-center justify-center border font-bold text-xs rounded-sm transition">
                                {{ link.label }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════
             Modal: Create / Edit User
        ═══════════════════════════════════════════════════════ -->
        <teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModals">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                    <!-- Panel -->
                    <div class="relative z-10 w-full max-w-lg bg-surface border border-border rounded-sm shadow-2xl overflow-hidden">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-container/40">
                            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-text flex items-center gap-3">
                                <i :class="['fa-solid text-primary', editingUser ? 'fa-user-pen' : 'fa-user-plus']"></i>
                                {{ editingUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}
                            </h2>
                            <button @click="closeModals" class="text-muted hover:text-text transition">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <!-- Form -->
                        <form @submit.prevent="submitForm" class="p-6 space-y-4">
                            <!-- Name & Email -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                                    <input v-model="form.name" type="text" placeholder="Nama lengkap"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Email <span class="text-rose-500">*</span></label>
                                    <input v-model="form.email" type="email" placeholder="email@example.com"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                    <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">
                                    Password <span v-if="!editingUser" class="text-rose-500">*</span>
                                    <span v-else class="text-muted/60 normal-case tracking-normal font-normal">(kosongkan jika tidak diganti)</span>
                                </label>
                                <input v-model="form.password" type="password" :placeholder="editingUser ? '••••••••' : 'Min. 8 karakter'"
                                    class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
                            </div>

                            <!-- Phone & Role -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">No. Telepon</label>
                                    <input v-model="form.phone" type="text" placeholder="08xxxxxxxxxx"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Role <span class="text-rose-500">*</span></label>
                                    <select v-model="form.role"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-bold transition">
                                        <option value="pelanggan">Pelanggan</option>
                                        <option value="operator">Operator</option>
                                        <option value="kurir">Kurir</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <p v-if="form.errors.role" class="mt-1 text-xs text-rose-600">{{ form.errors.role }}</p>
                                </div>
                            </div>

                            <!-- Address -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Alamat</label>
                                <textarea v-model="form.address" rows="2" placeholder="Alamat lengkap..."
                                    class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition resize-none"></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-3 pt-2 border-t border-border">
                                <button type="button" @click="closeModals"
                                    class="px-6 py-2.5 border border-border rounded-sm text-xs font-black uppercase tracking-widest text-muted hover:text-text hover:bg-container transition">
                                    Batal
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="px-6 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-sm text-xs font-black uppercase tracking-widest transition disabled:opacity-60 flex items-center gap-2">
                                    <i v-if="form.processing" class="fa-solid fa-circle-notch animate-spin"></i>
                                    {{ editingUser ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </teleport>

        <!-- ═══════════════════════════════════════════════════════
             Modal: Konfirmasi Delete
        ═══════════════════════════════════════════════════════ -->
        <teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModals">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative z-10 w-full max-w-sm bg-surface border border-rose-200 rounded-sm shadow-2xl p-6 space-y-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-text uppercase tracking-tight">Hapus Pengguna?</h3>
                                <p class="text-xs text-muted mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
                            </div>
                        </div>
                        <p class="text-sm text-text">
                            Anda akan menghapus akun <span class="font-bold">{{ deletingUser?.name }}</span>
                            (<span class="font-mono text-xs text-muted">{{ deletingUser?.email }}</span>).
                        </p>
                        <div class="flex gap-3 pt-2 border-t border-border">
                            <button @click="closeModals" class="flex-1 py-2.5 border border-border rounded-sm text-xs font-black uppercase tracking-widest text-muted hover:text-text hover:bg-container transition">
                                Batal
                            </button>
                            <button @click="confirmDelete" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-sm text-xs font-black uppercase tracking-widest transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-trash-can"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

    </DashboardLayout>
</template>

<style scoped>
.font-mono { font-family: 'Space Mono', monospace; }
</style>