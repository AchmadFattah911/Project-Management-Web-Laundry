<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
        default: false
    },
    canRegister: {
        type: Boolean,
        default: false
    },
});

const isMenuOpen = ref(false);

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};
</script>

<template>
    <div v-if="isMenuOpen"
         @click="isMenuOpen = false"
         class="fixed inset-0 bg-black/50 z-[60] lg:hidden backdrop-blur-sm">
    </div>

    <nav class="fixed top-0 left-0 right-0 z-50 bg-primary/90 backdrop-blur-md border-b border-white/10">
        <div class="flex justify-between items-center px-6 py-3 max-w-screen-2xl mx-auto text-white">
            <Link :href="route('home')" class="flex items-center opacity-90 hover:opacity-100 transition">
                <img src="logo.png" alt="HiWash Logo" class="h-8 w-8 rounded-full">
            </Link>

            <div class="hidden lg:flex items-center gap-8 text-[13px] font-medium tracking-wide">
                <Link :href="route('home')" class="text-secondary transition-colors">Beranda</Link>
                <a href="#layanan" class="text-white/70 hover:text-white transition-colors">Layanan</a>
                <a href="#kontak" class="text-white/70 hover:text-white transition-colors">Kontak</a>
                <a href="#faq" class="text-white/70 hover:text-white transition-colors">FAQ</a>
            </div>

            <div class="flex items-center gap-5 text-[13px] font-medium">
                <button aria-label="Search" class="hidden lg:block text-white/70 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>

                <div class="hidden lg:flex items-center gap-5">
                    <template v-if="canLogin">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')"
                            class="px-4 py-1.5 rounded border border-white/20 hover:bg-white hover:text-primary transition-all duration-300">
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link :href="route('login')" class="text-white/70 hover:text-white transition">Masuk</Link>
                            <Link v-if="canRegister" :href="route('register')"
                                class="px-4 py-1.5 rounded bg-secondary text-primary font-semibold hover:bg-secondary/90 transition-all">
                                Daftar
                            </Link>
                        </template>
                    </template>
                </div>

                <button @click="isMenuOpen = true" class="lg:hidden text-white focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <aside :class="[
        'fixed top-0 right-0 h-full w-64 bg-primary z-[70] shadow-2xl transform transition-transform duration-300 ease-in-out lg:hidden',
        isMenuOpen ? 'translate-x-0' : 'translate-x-full'
    ]">
        <div class="flex flex-col p-6 gap-6">
            <button @click="isMenuOpen = false" class="self-end text-white/70 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <nav class="flex flex-col gap-4 text-white font-medium">
                <Link :href="route('home')" @click="isMenuOpen = false" class="hover:text-secondary transition">Beranda</Link>
                <a href="#layanan" @click="isMenuOpen = false" class="hover:text-secondary transition">Layanan</a>
                <a href="#kontak" @click="isMenuOpen = false" class="hover:text-secondary transition">Kontak</a>
                <a href="#faq" @click="isMenuOpen = false" class="hover:text-secondary transition">FAQ</a>
            </nav>

            <hr class="border-white/10">

            <div class="flex flex-col gap-4">
                <template v-if="canLogin">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" @click="isMenuOpen = false"
                        class="text-center px-4 py-2 rounded border border-white/20 text-white">
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" @click="isMenuOpen = false" class="text-white/70 hover:text-white">Masuk</Link>
                        <Link v-if="canRegister" :href="route('register')" @click="isMenuOpen = false"
                            class="text-center px-4 py-2 rounded bg-secondary text-primary font-bold">
                            Daftar
                        </Link>
                    </template>
                </template>
            </div>
        </div>
    </aside>
</template>
