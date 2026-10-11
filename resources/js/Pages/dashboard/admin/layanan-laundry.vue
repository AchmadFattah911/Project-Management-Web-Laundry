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

// ── Props ────────────────────────────────────────────────────────
const props = defineProps({
    services:     Object,   // paginated
    stats:        Object,
    filters:      Object,
    categoryList: Array,
    chartData:    Object,
});

const flash = computed(() => usePage().props.flash ?? {});

// ── Search / Filter ──────────────────────────────────────────────
const search         = ref(props.filters?.search   ?? '');
const categoryFilter = ref(props.filters?.category ?? '');

let searchTimeout = null;
watch([search, categoryFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.services'), {
            search:   search.value,
            category: categoryFilter.value,
        }, { preserveState: true, replace: true });
    }, 350);
});

// ── Collapsible chart ────────────────────────────────────────────
const isChartExpanded = ref(true);
onMounted(() => {
    const saved = localStorage.getItem('layanan-laundry-chart-expanded');
    if (saved !== null) isChartExpanded.value = saved === 'true';
});
watch(isChartExpanded, v => localStorage.setItem('layanan-laundry-chart-expanded', v));

// ── Modals ───────────────────────────────────────────────────────
const showFormModal   = ref(false);
const showDeleteModal = ref(false);
const editingService  = ref(null);
const deletingService = ref(null);

function openCreate() {
    editingService.value = null;
    form.reset();
    form.clearErrors();
    showFormModal.value = true;
}

function openEdit(s) {
    editingService.value = s;
    form.name        = s.name;
    form.category    = s.category;
    form.price       = s.price;
    form.estimate    = s.estimate;
    form.status      = s.status;
    form.description = s.description ?? '';
    form.clearErrors();
    showFormModal.value = true;
}

function openDelete(s) {
    deletingService.value = s;
    showDeleteModal.value = true;
}

function closeModals() {
    showFormModal.value   = false;
    showDeleteModal.value = false;
    editingService.value  = null;
    deletingService.value = null;
}

// ── Inertia Form ─────────────────────────────────────────────────
const form = useForm({
    name: '', category: 'Kiloan', price: '', estimate: '', status: 'tersedia', description: '',
});

function submitForm() {
    if (editingService.value) {
        form.put(route('admin.services.update', editingService.value.id), {
            onSuccess: closeModals,
        });
    } else {
        form.post(route('admin.services.store'), {
            onSuccess: closeModals,
        });
    }
}

function confirmDelete() {
    router.delete(route('admin.services.destroy', deletingService.value.id), {
        onSuccess: closeModals,
    });
}

// ── Helpers ──────────────────────────────────────────────────────
function formatRupiah(val) {
    if (val === null || val === undefined) return 'Rp 0';
    return 'Rp ' + parseFloat(val).toLocaleString('id-ID');
}

const STATUS_MAP = {
    tersedia:       { label: 'Tersedia',       cls: 'bg-emerald-100 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    sibuk:          { label: 'Sibuk',          cls: 'bg-amber-100 text-amber-700 border-amber-200',       dot: 'bg-amber-500' },
    tidak_tersedia: { label: 'Tidak Tersedia', cls: 'bg-rose-100 text-rose-700 border-rose-200',          dot: 'bg-rose-500' },
};
const getStatus   = (s) => STATUS_MAP[s] ?? { label: s, cls: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };

const CAT_COLORS = ['bg-blue-100 text-blue-700 border-blue-200','bg-indigo-100 text-indigo-700 border-indigo-200','bg-violet-100 text-violet-700 border-violet-200','bg-cyan-100 text-cyan-700 border-cyan-200'];
const catColorMap = computed(() => {
    const map = {};
    (props.categoryList ?? []).forEach((c, i) => { map[c] = CAT_COLORS[i % CAT_COLORS.length]; });
    return map;
});
const getCatClass = (cat) => catColorMap.value[cat] ?? 'bg-slate-100 text-slate-700 border-slate-200';

// ── Stat cards ────────────────────────────────────────────────────
const statCards = computed(() => [
    { label: 'Total Layanan',  value: props.stats?.total    ?? 0, icon: 'fa-list-check',     color: 'text-blue-600',    bg: 'bg-blue-500/10' },
    { label: 'Tersedia',       value: props.stats?.tersedia ?? 0, icon: 'fa-circle-check',   color: 'text-emerald-600', bg: 'bg-emerald-500/10' },
    { label: 'Sedang Sibuk',   value: props.stats?.sibuk    ?? 0, icon: 'fa-spinner',        color: 'text-amber-600',   bg: 'bg-amber-500/10' },
    { label: 'Tidak Tersedia', value: props.stats?.nonaktif ?? 0, icon: 'fa-ban',            color: 'text-rose-600',    bg: 'bg-rose-500/10' },
]);

// ── Chart Configs ─────────────────────────────────────────────────
const tooltipDefaults = {
    backgroundColor: '#0A0A0B',
    titleFont: { family: 'Space Mono', size: 10 },
    bodyFont: { family: 'Inter', size: 12, weight: 'bold' },
    padding: 12, cornerRadius: 4,
};
const doughnutOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'right', labels: { font: { family: 'Inter', size: 10, weight: 'bold' }, color: '#888' } }, tooltip: { ...tooltipDefaults, displayColors: true } },
    cutout: '70%',
};
const lineOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { font: { family: 'Inter', size: 10, weight: 'bold' }, color: '#888' } }, tooltip: { ...tooltipDefaults, displayColors: true } },
    scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } },
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } },
    },
};
const barOptions = {
    responsive: true, maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults } },
    scales: {
        x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, border: { display: false }, ticks: { font: { family: 'Space Mono', size: 9 }, color: '#888' } },
        y: { grid: { display: false }, border: { display: false }, ticks: { font: { family: 'Inter', size: 9 }, color: '#888' } },
    },
};

const catPalette = ['#5B4CF3','#059669','#d97706','#e11d48','#0891b2','#8b5cf6'];

const chartCategoryData = computed(() => {
    const cats = props.chartData?.categories ?? [];
    return {
        labels: cats.map(c => c.category),
        datasets: [{ data: cats.map(c => c.total), backgroundColor: cats.map((_, i) => catPalette[i % catPalette.length]), borderWidth: 0, hoverOffset: 4 }],
    };
});

const chartTrendData = computed(() => ({
    labels: (props.chartData?.trendMonths ?? []).map(m => m.label),
    datasets: [
        { label: 'Kiloan', data: (props.chartData?.trendMonths ?? []).map(m => m.kiloan), borderColor: '#5B4CF3', backgroundColor: 'transparent', tension: 0.4, pointBackgroundColor: '#fff', pointBorderColor: '#5B4CF3', pointBorderWidth: 2, pointHoverRadius: 5 },
        { label: 'Satuan', data: (props.chartData?.trendMonths ?? []).map(m => m.satuan), borderColor: '#059669', backgroundColor: 'transparent', tension: 0.4, pointBackgroundColor: '#fff', pointBorderColor: '#059669', pointBorderWidth: 2, pointHoverRadius: 5 },
    ],
}));

const chartStatusData = computed(() => ({
    labels: ['Tersedia', 'Sibuk', 'Tidak Tersedia'],
    datasets: [{ data: props.chartData?.statusDist ?? [0,0,0], backgroundColor: ['#059669','#d97706','#e11d48'], borderWidth: 0, hoverOffset: 4 }],
}));

const chartPriceData = computed(() => ({
    labels: (props.chartData?.priceChart ?? []).map(p => p.name),
    datasets: [{ label: 'Harga (Rp)', data: (props.chartData?.priceChart ?? []).map(p => p.price), backgroundColor: '#5B4CF3', borderRadius: 4 }],
}));
</script>

<template>
    <Head title="Manajemen Layanan - Hi Wash" />

    <DashboardLayout title="Layanan Laundry">
        <div class="space-y-10 pb-20">

            <!-- Flash -->
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
                        Layanan <span class="text-muted font-medium">Laundry</span>
                    </h1>
                    <p class="text-muted font-medium italic">Atur jenis layanan, harga dasar, dan estimasi pengerjaan.</p>
                </div>
                <button @click="openCreate"
                    class="bg-primary hover:bg-primary-hover text-white px-8 py-4 rounded-sm font-black uppercase tracking-widest text-xs shadow-lg transition-all transform hover:-translate-y-1 flex items-center gap-3">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Layanan
                </button>
            </header>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="(stat, i) in statCards" :key="i"
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
                    class="w-full flex items-center justify-between px-6 py-4 hover:bg-container/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs">
                            <i class="fa-solid fa-chart-bar"></i>
                        </div>
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-text">Visual Analitik - Portofolio Layanan</h3>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[9px] font-mono text-muted uppercase tracking-widest italic">Live Trend Analysis</span>
                        </div>
                        <i :class="['fa-solid transition-transform duration-300', isChartExpanded ? 'fa-chevron-up' : 'fa-chevron-down text-primary']"></i>
                    </div>
                </button>

                <transition enter-active-class="transition-all duration-500 ease-out" leave-active-class="transition-all duration-300 ease-in"
                    enter-from-class="max-h-0 opacity-0" enter-to-class="max-h-[2000px] opacity-100"
                    leave-from-class="max-h-[2000px] opacity-100" leave-to-class="max-h-0 opacity-0">
                    <div v-show="isChartExpanded" class="p-6 border-t border-dashed border-border overflow-hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-12">
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Distribusi Kategori Layanan</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Doughnut :data="chartCategoryData" :options="doughnutOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Trend Pemesanan per Kategori (6 Bln)</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Line :data="chartTrendData" :options="lineOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Status Ketersediaan</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Doughnut :data="chartStatusData" :options="doughnutOptions" />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-4">
                                <div class="border-b border-dashed border-border pb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-text">Perbandingan Harga Layanan</h4>
                                </div>
                                <div class="h-48 md:h-56 w-full relative">
                                    <Bar :data="chartPriceData" :options="barOptions" />
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Table -->
            <div class="space-y-4">
                <div class="flex flex-col md:flex-row gap-4 justify-between items-end">
                    <div class="w-full md:max-w-md relative group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-primary transition-colors"></i>
                        <input v-model="search" type="text" placeholder="Cari nama atau deskripsi layanan..."
                            class="w-full pl-12 pr-4 py-3 bg-white border border-border rounded-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-sm" />
                    </div>
                    <select v-model="categoryFilter"
                        class="px-4 py-3 border border-border bg-surface text-text rounded-sm text-xs font-bold uppercase tracking-widest outline-none focus:border-primary transition">
                        <option value="">Semua Kategori</option>
                        <option v-for="cat in categoryList" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>

                <div class="bg-surface border border-border rounded-sm shadow-xl overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[860px]">
                        <thead>
                            <tr class="bg-container/50 border-b border-border">
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Nama Layanan</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Kategori</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Estimasi</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Harga Dasar</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted">Pesanan</th>
                                <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="services.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-muted italic text-sm">
                                    <i class="fa-solid fa-inbox text-2xl mb-2 block opacity-40"></i>
                                    Tidak ada layanan ditemukan.
                                </td>
                            </tr>
                            <tr v-for="s in services.data" :key="s.id" class="hover:bg-container/30 transition-colors group">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-bold text-text group-hover:text-primary transition-colors">{{ s.name }}</p>
                                        <p v-if="s.description" class="text-[11px] text-muted mt-0.5 line-clamp-1">{{ s.description }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getCatClass(s.category)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border">
                                        {{ s.category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 text-muted">
                                        <i class="fa-regular fa-clock text-xs"></i>
                                        <span class="text-xs font-bold uppercase">{{ s.estimate }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold text-text bg-container/50 px-3 py-1.5 rounded-sm border border-border block w-fit text-xs">
                                        {{ formatRupiah(s.price) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div :class="getStatus(s.status).dot" class="w-2 h-2 rounded-full shrink-0"></div>
                                        <span :class="getStatus(s.status).cls" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest border whitespace-nowrap">
                                            {{ getStatus(s.status).label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold text-text text-sm">{{ s.orders_count }}</span>
                                    <span class="text-[10px] text-muted ml-1">pesanan</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openEdit(s)"
                                            class="w-8 h-8 flex items-center justify-center rounded-sm border border-border text-muted hover:bg-text hover:text-white transition-all" title="Edit">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button @click="openDelete(s)"
                                            class="w-8 h-8 flex items-center justify-center rounded-sm border border-border text-muted hover:bg-rose-600 hover:border-rose-600 hover:text-white transition-all" title="Hapus">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-2 pt-4">
                    <p class="text-[10px] font-bold text-muted uppercase tracking-widest">
                        Menampilkan {{ services.from ?? 0 }}–{{ services.to ?? 0 }} dari {{ services.total }} layanan
                    </p>
                    <div class="flex gap-2">
                        <template v-for="link in services.links" :key="link.label">
                            <button v-if="link.label === '&laquo; Previous'" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url"
                                class="w-8 h-8 flex items-center justify-center border border-border text-muted rounded-sm disabled:opacity-40 disabled:cursor-not-allowed hover:bg-container transition">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </button>
                            <button v-else-if="link.label === 'Next &raquo;'" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url"
                                class="w-8 h-8 flex items-center justify-center border border-border text-muted rounded-sm disabled:opacity-40 disabled:cursor-not-allowed hover:bg-container transition">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </button>
                            <button v-else @click="link.url && router.get(link.url, {}, { preserveState: true })"
                                :class="link.active ? 'bg-primary text-white border-primary' : 'border-border text-text hover:bg-container'"
                                class="w-8 h-8 flex items-center justify-center border font-bold text-xs rounded-sm transition">
                                {{ link.label }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════
             Modal: Create / Edit Service
        ══════════════════════════════════════ -->
        <teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModals">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative z-10 w-full max-w-lg bg-surface border border-border rounded-sm shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-container/40 shrink-0">
                            <h2 class="text-sm font-black uppercase tracking-[0.2em] text-text flex items-center gap-3">
                                <i :class="['fa-solid text-primary', editingService ? 'fa-pen-to-square' : 'fa-plus']"></i>
                                {{ editingService ? 'Edit Layanan' : 'Tambah Layanan Baru' }}
                            </h2>
                            <button @click="closeModals" class="text-muted hover:text-text transition">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <!-- Form -->
                        <form @submit.prevent="submitForm" class="p-6 space-y-4 overflow-y-auto">
                            <!-- Name -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Nama Layanan <span class="text-rose-500">*</span></label>
                                <input v-model="form.name" type="text" placeholder="contoh: Cuci Komplit Reguler"
                                    class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                            </div>

                            <!-- Category & Status -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Kategori <span class="text-rose-500">*</span></label>
                                    <input v-model="form.category" type="text" placeholder="Kiloan / Satuan / ..."
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                    <p v-if="form.errors.category" class="mt-1 text-xs text-rose-600">{{ form.errors.category }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Status <span class="text-rose-500">*</span></label>
                                    <select v-model="form.status"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-bold transition">
                                        <option value="tersedia">Tersedia</option>
                                        <option value="sibuk">Sibuk</option>
                                        <option value="tidak_tersedia">Tidak Tersedia</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Price & Estimate -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Harga Dasar (Rp) <span class="text-rose-500">*</span></label>
                                    <input v-model="form.price" type="number" min="0" placeholder="0"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                    <p v-if="form.errors.price" class="mt-1 text-xs text-rose-600">{{ form.errors.price }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Estimasi Waktu <span class="text-rose-500">*</span></label>
                                    <input v-model="form.estimate" type="text" placeholder="contoh: 1-2 Hari"
                                        class="w-full px-4 py-2.5 border border-border rounded-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm font-medium transition" />
                                    <p v-if="form.errors.estimate" class="mt-1 text-xs text-rose-600">{{ form.errors.estimate }}</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1">Deskripsi</label>
                                <textarea v-model="form.description" rows="3" placeholder="Deskripsi singkat layanan..."
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
                                    {{ editingService ? 'Simpan Perubahan' : 'Tambah Layanan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </teleport>

        <!-- ══════════════════════════════════════
             Modal: Konfirmasi Delete
        ══════════════════════════════════════ -->
        <teleport to="body">
            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModals">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative z-10 w-full max-w-sm bg-surface border border-rose-200 rounded-sm shadow-2xl p-6 space-y-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-text uppercase tracking-tight">Hapus Layanan?</h3>
                                <p class="text-xs text-muted mt-0.5">Layanan yang memiliki pesanan tidak dapat dihapus.</p>
                            </div>
                        </div>
                        <p class="text-sm text-text">
                            Anda akan menghapus layanan
                            <span class="font-bold">{{ deletingService?.name }}</span>
                            <span v-if="deletingService?.orders_count > 0" class="block mt-1 text-rose-600 text-xs font-bold">
                                ⚠ Layanan ini memiliki {{ deletingService?.orders_count }} pesanan, tidak bisa dihapus.
                            </span>
                        </p>
                        <div class="flex gap-3 pt-2 border-t border-border">
                            <button @click="closeModals" class="flex-1 py-2.5 border border-border rounded-sm text-xs font-black uppercase tracking-widest text-muted hover:text-text hover:bg-container transition">
                                Batal
                            </button>
                            <button @click="confirmDelete" :disabled="deletingService?.orders_count > 0"
                                class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-sm text-xs font-black uppercase tracking-widest transition flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
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