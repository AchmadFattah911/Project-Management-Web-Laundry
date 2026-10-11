<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);

defineProps({
    title: {
        type: String,
        default: 'Admin Console'
    }
});

defineEmits(['toggle-sidebar']);
</script>

<template>
    <header class="h-20 flex items-center justify-between px-4 md:px-8 bg-surface border-b border-border sticky top-0 z-40 transition-all">
        <div class="flex items-center gap-4">
            <!-- Hamburger Menu Button (Mobile Only) -->
            <button 
                @click="$emit('toggle-sidebar')"
                class="lg:hidden p-2 rounded-lg hover:bg-container text-muted hover:text-primary transition"
                aria-label="Toggle Sidebar"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Title area -->
            <h1 class="text-lg md:text-xl font-bold text-primary truncate max-w-[150px] md:max-w-none">{{ title }}</h1>
        </div>

        <!-- Search Bar (Hidden on very small screens) -->
        <div class="hidden sm:flex flex-1 max-w-xl px-4 md:px-12">
            <div class="relative group w-full">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted transition group-focus-within:text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input 
                    type="text" 
                    placeholder="Search..." 
                    class="block w-full pl-10 pr-3 py-2 border border-border rounded-full bg-container text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all shadow-sm"
                >
            </div>
        </div>

        <!-- Right Side: Actions & Profile -->
        <div class="flex items-center gap-2 md:gap-6">
            <button class="relative p-2 text-muted hover:text-primary transition rounded-full hover:bg-container hidden xs:flex">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <div class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 border-2 border-white"></div>
            </button>
            
            <div class="h-8 w-px bg-border hidden md:block"></div>

            <button class="flex items-center gap-3 group focus:outline-none">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold border-2 border-transparent group-hover:border-primary/20 transition overflow-hidden shadow-sm">
                    <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" alt="Profile" class="w-full h-full object-cover">
                    <span v-else>{{ user?.name ? user.name.charAt(0).toUpperCase() : 'A' }}</span>
                </div>
                <div class="hidden xl:flex flex-col items-start leading-none gap-1">
                    <span class="text-sm font-bold text-text">{{ user?.name || 'Administrator' }}</span>
                    <span class="text-[10px] text-muted font-bold uppercase tracking-wider">{{ user?.role || 'Admin' }}</span>
                </div>
            </button>
        </div>
    </header>
</template>
