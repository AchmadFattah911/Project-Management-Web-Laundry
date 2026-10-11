<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/dashboard.vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Dashboard Kurir - Hi Wash Laundry" />

    <DashboardLayout title="Overview">
        <!-- Main Content -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-text tracking-tight mb-2">Halo, {{ user?.name || 'Kurir' }} 👋</h1>
            <p class="text-muted text-sm">Panel kurir pengiriman laundry. Pantau tugas antar-jemput Anda hari ini.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Antar Hari Ini</p>
                     <div class="p-2 bg-yellow-50 text-yellow-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">belum ada tugas baru</p>
                </div>
            </div>
            
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Dalam Perjalanan</p>
                     <div class="p-2 bg-blue-50 text-blue-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">sedang diantar</p>
                </div>
            </div>
            
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Terselesaikan</p>
                     <div class="p-2 bg-green-50 text-green-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">pengantaran berhasil</p>
                </div>
            </div>
        </div>

        <!-- Placeholder table area -->
        <div class="bg-surface rounded-2xl border border-border/50 shadow-sm p-12 text-center text-muted flex flex-col items-center justify-center min-h-[300px]">
            <div class="w-16 h-16 bg-container rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-text mb-1">Tidak Ada Pengiriman</h3>
            <p class="text-sm max-w-sm mb-6">Anda tidak memiliki tugas pengiriman saat ini. Daftar pengiriman akan tampil di sini saat ditugaskan.</p>
        </div>
    </DashboardLayout>
</template>
