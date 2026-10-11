<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardLayout from '@/Layouts/dashboard.vue';

const user = computed(() => usePage().props.auth.user);

const props = defineProps({
    stats: Object,
    recentOrders: Array,
    orderChart: Array,
});

// ── Helpers ──────────────────────────────────────────────────────────
function formatRupiah(val) {
    if (!val && val !== 0) return 'Rp 0';
    const num = parseFloat(val);
    if (num >= 1_000_000_000) return 'Rp ' + (num / 1_000_000_000).toFixed(1) + 'M';
    if (num >= 1_000_000)     return 'Rp ' + (num / 1_000_000).toFixed(1) + 'Jt';
    if (num >= 1_000)         return 'Rp ' + (num / 1_000).toFixed(0) + 'rb';
    return 'Rp ' + num.toLocaleString('id-ID');
}

function formatTrend(val) {
    if (val === null || val === undefined) return 'Bulan ini';
    return (val >= 0 ? '+' : '') + val + '%';
}

function trendColor(val) {
    if (val === null || val === undefined) return 'text-sky-600 bg-sky-50';
    return val >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50';
}

function statusLabel(status) {
    const map = {
        pending:  { label: 'PENDING',       cls: 'text-amber-600 bg-amber-50' },
        diproses: { label: 'DIPROSES',      cls: 'text-blue-600 bg-blue-50' },
        selesai:  { label: 'SELESAI',       cls: 'text-emerald-600 bg-emerald-50' },
        diantar:  { label: 'SEDANG DIANTAR', cls: 'text-violet-600 bg-violet-50' },
    };
    return map[status] ?? { label: status?.toUpperCase() ?? '-', cls: 'text-muted bg-container' };
}

// ── Stat cards config ─────────────────────────────────────────────────
const statCards = computed(() => [
    {
        label: 'Total Pesanan',
        value: (props.stats?.totalOrders ?? 0).toLocaleString('id-ID'),
        trend: formatTrend(props.stats?.orderTrend),
        trendCls: trendColor(props.stats?.orderTrend),
        icon: 'fa-box-archive',
        color: 'text-blue-600',
        bg: 'bg-blue-500/10',
    },
    {
        label: 'Total Pengguna',
        value: (props.stats?.totalUsers ?? 0).toLocaleString('id-ID'),
        trend: formatTrend(props.stats?.userTrend),
        trendCls: trendColor(props.stats?.userTrend),
        icon: 'fa-users',
        color: 'text-emerald-600',
        bg: 'bg-emerald-500/10',
    },
    {
        label: 'Pesanan Hari Ini',
        value: (props.stats?.todayOrders ?? 0).toLocaleString('id-ID'),
        trend: 'Hari Ini',
        trendCls: 'text-sky-600 bg-sky-50',
        icon: 'fa-clock',
        color: 'text-amber-600',
        bg: 'bg-amber-500/10',
    },
    {
        label: 'Pendapatan',
        value: formatRupiah(props.stats?.revenue),
        trend: formatTrend(props.stats?.revenueTrend),
        trendCls: trendColor(props.stats?.revenueTrend),
        icon: 'fa-wallet',
        color: 'text-rose-600',
        bg: 'bg-rose-500/10',
    },
]);

// ── Chart bar heights ─────────────────────────────────────────────────
const chartMax = computed(() => {
    const vals = (props.orderChart ?? []).map(m => m.value);
    return Math.max(...vals, 1);
});
</script>

<template>
    <Head title="Dashboard Overview - Hi Wash Laundry" />

    <DashboardLayout title="Overview">
        <div class="space-y-10 pb-20">
            <!-- Header -->
            <header class="relative">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div class="space-y-4">
                        <h1 class="text-4xl md:text-5xl font-bold tracking-tighter text-text leading-none">
                            Dashboard <span class="text-muted font-medium">Status</span>
                        </h1>
                        <p class="text-muted font-medium max-w-xl">
                            Selamat datang kembali,
                            <span class="text-text font-bold">{{ user?.name || 'Administrator' }}</span>.
                            Pantau performa operasional laundry Anda secara real-time.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="(item, index) in statCards"
                    :key="index"
                    class="bg-surface border border-border p-6 rounded-sm shadow-sm relative group hover:border-primary/50 transition-all"
                >
                    <div class="absolute top-3 right-3 text-[8px] font-mono font-bold text-muted/50 uppercase tracking-tighter">
                        #Snapshot_{{ index + 1 }}
                    </div>

                    <div class="flex items-center gap-4 mb-4">
                        <div :class="[item.bg, item.color]" class="w-10 h-10 rounded-full flex items-center justify-center text-sm transition-transform group-hover:scale-110">
                            <i :class="['fa-solid', item.icon]"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-muted">{{ item.label }}</span>
                    </div>

                    <div class="flex items-baseline gap-2">
                        <h3 class="text-3xl font-black text-text tracking-tighter">{{ item.value }}</h3>
                        <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', item.trendCls]">{{ item.trend }}</span>
                    </div>
                </div>
            </div>

            <!-- Order Trend Chart -->
            <div class="bg-surface border-2 border-dashed border-border rounded-xl p-8 relative overflow-hidden">
                <div class="flex items-center justify-between mb-10 border-b border-dashed border-border pb-4">
                    <h3 class="text-xs font-bold uppercase tracking-[0.3em] text-text flex items-center gap-3">
                        <i class="fa-solid fa-chart-column text-primary"></i>
                        Tren Pesanan 6 Bulan Terakhir
                    </h3>
                    <div class="flex gap-2 items-center">
                        <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                        <span class="text-[9px] font-mono text-muted uppercase tracking-widest italic">Live Data Tracking</span>
                    </div>
                </div>

                <div class="flex items-end gap-3 h-40">
                    <div
                        v-for="(month, i) in (orderChart ?? [])"
                        :key="i"
                        class="flex-1 flex flex-col items-center gap-2 group"
                    >
                        <!-- bar -->
                        <div class="w-full relative flex items-end" style="height: 100px;">
                            <div
                                class="w-full bg-primary/30 group-hover:bg-primary transition-colors rounded-t-sm"
                                :style="{ height: chartMax > 0 ? ((month.value / chartMax) * 100) + '%' : '4px' }"
                                :title="month.value + ' pesanan'"
                            ></div>
                        </div>
                        <!-- value -->
                        <span class="text-[10px] font-bold text-text">{{ month.value }}</span>
                        <!-- label -->
                        <span class="text-[9px] font-mono text-muted uppercase tracking-wider">{{ month.label }}</span>
                    </div>

                    <!-- fallback kalau data kosong -->
                    <div v-if="!orderChart || orderChart.length === 0" class="w-full text-center text-muted text-xs italic py-8">
                        Belum ada data pesanan.
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold italic font-serif text-text border-l-4 border-primary pl-4 uppercase tracking-tighter">
                        Antrean Pesanan Terbaru
                    </h3>
                    <button
                        class="text-[10px] font-black uppercase tracking-widest text-primary hover:underline"
                        @click="router.visit(route('admin.orders'))"
                    >
                        Lihat Semua <i class="fa-solid fa-arrow-right ml-1"></i>
                    </button>
                </div>

                <div class="bg-surface border border-border rounded-sm divide-y divide-border shadow-lg">
                    <!-- Empty state -->
                    <div v-if="!recentOrders || recentOrders.length === 0" class="p-12 text-center space-y-3">
                        <i class="fa-solid fa-inbox text-3xl text-muted/40"></i>
                        <p class="text-muted text-sm italic">Belum ada pesanan masuk.</p>
                    </div>

                    <!-- Order rows -->
                    <div
                        v-for="order in recentOrders"
                        :key="order.id"
                        class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-container/30 transition-colors group"
                    >
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 bg-container border border-border flex flex-col items-center justify-center font-mono text-[10px] font-bold uppercase shrink-0">
                                <span class="text-primary">INV</span>
                                <span>#{{ String(order.id).padStart(3, '0') }}</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-text group-hover:text-primary transition-colors">
                                    {{ order.customer }} — {{ order.service }}
                                </h4>
                                <p class="text-[11px] text-muted font-medium italic">{{ order.created_at }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-6">
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] font-bold uppercase text-muted tracking-widest">Total</p>
                                <p class="text-xs font-bold text-text">{{ formatRupiah(order.total_price) }}</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] font-bold uppercase text-muted tracking-widest">Status</p>
                                <span :class="['text-[10px] font-bold px-2 py-0.5 rounded', statusLabel(order.status).cls]">
                                    {{ statusLabel(order.status).label }}
                                </span>
                            </div>
                            <button class="w-10 h-10 border border-border flex items-center justify-center text-muted hover:bg-text hover:text-white transition-all rounded-sm">
                                <i class="fa-solid fa-arrow-right-long"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<style scoped>
.font-serif {
    font-family: 'DM Serif Display', serif;
}
.font-mono {
    font-family: 'Space Mono', monospace;
}
</style>