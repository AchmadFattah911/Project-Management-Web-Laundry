<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['update:isOpen']);

const page = usePage();
const user = computed(() => page.props.auth?.user || { role: 'admin' });
const role = computed(() => (user.value.role || 'admin').toLowerCase());

const allLinks = computed(() => ({
    admin: [
        { name: 'Overview', href: route('admin.dashboard'), active: route().current('admin.dashboard'), icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
        { name: 'Manajemen User', href: route('admin.users'), active: route().current('admin.users'), icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
        { name: 'Manajemen Order', href: route('admin.orders'), active: route().current('admin.orders'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
        { 
            name: 'Layanan Laundry', 
            href: route('admin.services'), 
            active: route().current('admin.services'), 
            icon: 'M3 7h18M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4a3 3 0 016 0v3' 
        },
        { 
            name: 'Pickup & Delivery', 
            href: route('admin.pickup'), 
            active: route().current('admin.pickup'), 
            icon: 'M3 13h13v-2H3v2zm0 4h9v-2H3v2zm0-8h18V7H3v2zm13 8a2 2 0 100-4 2 2 0 000 4zm4 0a2 2 0 100-4 2 2 0 000 4z' 
        }
    ],
    operator: [
        { name: 'Overview', href: route('operator.dashboard'), active: route().current('operator.dashboard'), icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
        { name: 'Pesanan Masuk', href: '#', active: false, icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4' },
    ],
    kurir: [
        { name: 'Overview', href: route('kurir.dashboard'), active: route().current('kurir.dashboard'), icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
        { name: 'Tugas Jemput', href: '#', active: false, icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
    ]
}));

const links = computed(() => allLinks.value[role.value] || allLinks.value.admin);
</script>

<template>
    <aside 
        class="fixed top-0 left-0 h-full w-64 bg-primary text-white flex flex-col z-50 transition-transform duration-300 ease-in-out lg:translate-x-0"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <!-- Close Button (Mobile Only) -->
        <button 
            @click="emit('update:isOpen', false)"
            class="absolute top-4 right-4 lg:hidden p-2 rounded-lg bg-black/10 hover:bg-black/20 text-white transition-all"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Sidebar Header / Logo -->
        <div class="px-8 py-8 border-b border-white/10 flex items-center gap-3">
            <svg class="w-8 h-8 text-white fill-current" viewBox="0 0 24 24">
                 <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
            </svg>
            <div class="flex flex-col">
                <span class="font-bold text-xl tracking-tight leading-none text-white">HiWash</span>
                <span class="text-[10px] text-white/70 font-semibold tracking-widest uppercase mt-1">{{ role }} Dashboard</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 py-8 space-y-2 overflow-y-auto custom-scrollbar">
            <Link v-for="(link, index) in links" :key="index" :href="link.href" 
                  class="relative flex items-center gap-4 px-4 py-3 rounded-xl transition overflow-hidden group"
                  :class="link.active ? 'bg-black/10 text-white font-semibold' : 'text-white/80 hover:bg-black/5 hover:text-white'">
                <!-- Active Indicator (Yellow bar) -->
                <div v-if="link.active" class="absolute left-0 top-0 bottom-0 w-1 bg-secondary rounded-r-md"></div>
                
                <svg class="w-5 h-5 opacity-90 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                </svg>
                <span class="text-[15px]">{{ link.name }}</span>
            </Link>
        </nav>

        <!-- Bottom Actions -->
        <div class="p-6 space-y-4">
             <button class="w-full flex items-center justify-center gap-2 bg-secondary hover:bg-yellow-400 text-gray-900 font-bold py-3 px-4 rounded-xl shadow-sm transition transform hover:-translate-y-0.5 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>New Order</span>
            </button>

            <div class="pt-4 border-t border-white/10">
                <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-white/80 hover:bg-black/10 hover:text-white text-[15px] transition group">
                    <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span class="font-medium text-sm uppercase tracking-widest">Logout</span>
                </Link>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
</style>
