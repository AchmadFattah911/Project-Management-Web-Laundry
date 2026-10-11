<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/dashboard.vue';

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Dashboard Operator - Hi Wash Laundry" />

    <DashboardLayout title="Overview">
        <!-- Main Content -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-text tracking-tight mb-2">Halo, {{ user?.name || 'Operator' }} 👋</h1>
            <p class="text-muted text-sm">Panel operator laundry. Pantau antrian dan jadwal proses cuci hari ini.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Antrian Cuci</p>
                     <div class="p-2 bg-yellow-50 text-yellow-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">belum ada antrian baru</p>
                </div>
            </div>
            
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Sedang Diproses</p>
                     <div class="p-2 bg-blue-50 text-blue-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">pesanan sedang dicuci</p>
                </div>
            </div>
            
            <div class="bg-surface rounded-2xl p-6 shadow-sm border border-border/50 flex flex-col justify-between group hover:shadow-md transition">
                <div class="flex justify-between items-start mb-4">
                     <p class="text-muted text-sm font-medium">Selesai Hari Ini</p>
                     <div class="p-2 bg-green-50 text-green-600 rounded-xl group-hover:scale-110 transition-transform">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                     </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-text">0</h3>
                    <p class="text-xs text-muted mt-2 flex items-center gap-1">pesanan telah selesai</p>
                </div>
            </div>
        </div>

        <!-- Placeholder table area -->
        <div class="bg-surface rounded-2xl border border-border/50 shadow-sm p-12 text-center text-muted flex flex-col items-center justify-center min-h-[300px]">
            <div class="w-16 h-16 bg-container rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-text mb-1">Tidak Ada Antrian Baru</h3>
            <p class="text-sm max-w-sm mb-6">Anda tidak memiliki antrian pesanan saat ini. Daftar antrian cucian akan tampil di sini.</p>
        </div>
    </DashboardLayout>
</template>
