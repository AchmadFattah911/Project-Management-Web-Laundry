<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';
import Topbar from '@/Components/Topbar.vue';

defineProps({
    title: {
        type: String,
        default: 'Admin Console'
    }
});

const isSidebarOpen = ref(false);

// Close sidebar on navigation (mobile)
watch(() => usePage().url, () => {
    isSidebarOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen flex bg-container font-sans text-text relative flex-col lg:flex-row overflow-x-hidden">
        <!-- Backdrop for mobile -->
        <transition 
            enter-active-class="transition-opacity ease-linear duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="isSidebarOpen" 
                class="fixed inset-0 bg-black/50 z-[45] lg:hidden backdrop-blur-sm"
                @click="isSidebarOpen = false"
            ></div>
        </transition>

        <!-- Sidebar -->
        <Sidebar :is-open="isSidebarOpen" @update:is-open="val => isSidebarOpen = val" />

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out"
             :class="[isSidebarOpen ? 'lg:ml-64' : 'lg:ml-64']"> <!-- Static margin on desktop, dynamic on future mini-sidebar -->
            
            <!-- Topbar (Sticky) -->
            <Topbar :title="title" @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />

            <!-- Page Content -->
            <main class="flex-1 p-4 md:p-8 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
