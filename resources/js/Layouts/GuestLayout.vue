<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <Link href="/">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-primary rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-black text-2xl italic">L</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-black tracking-tighter leading-none text-gray-800">LAUNDRY</span>
                        <span class="text-primary text-sm font-bold tracking-[0.2em]">KITA</span>
                    </div>
                </div>
            </Link>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-2xl">
            <slot />
        </div>
    </div>
</template>
