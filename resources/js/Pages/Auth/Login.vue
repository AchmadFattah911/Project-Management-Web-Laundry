<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk - Hi Wash Laundry" />

    <div class="flex min-h-screen bg-gray-100 font-sans">
        <div class="hidden lg:flex w-1/2 bg-red-600 flex-col items-center justify-center p-12 text-white relative overflow-hidden">
            <div class="absolute top-0 left-0 w-64 h-64 bg-red-500 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl opacity-50"></div>

            <div class="relative z-10 text-center">
                <div class="flex items-center justify-center mb-8">
                    <div class="flex items-center space-x-2">
                        <svg class="w-12 h-12 fill-white" viewBox="0 0 24 24"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/></svg>
                        <span class="text-3xl font-bold italic tracking-tighter">Hi Wash <span class="font-light not-italic">Laundry</span></span>
                    </div>
                </div>

                <div class="mt-10 relative inline-block">
                    <div class="w-64 h-80 bg-white rounded-2xl shadow-2xl relative border-8 border-gray-100">
                        <div class="h-1/4 border-b-4 border-gray-100 flex items-center justify-around px-4">
                            <div class="w-12 h-2 bg-gray-200 rounded"></div>
                            <div class="w-4 h-4 bg-gray-200 rounded-full"></div>
                        </div>
                        <div class="flex justify-center mt-8">
                            <div class="w-40 h-40 rounded-full border-8 border-gray-100 flex items-center justify-center overflow-hidden bg-blue-50">
                                <div class="w-full h-1/2 bg-blue-400 mt-auto animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -bottom-6 -left-10 w-32 h-20 bg-yellow-400 rounded-lg shadow-lg"></div>
                    <div class="absolute -bottom-6 -right-10 w-32 h-20 bg-blue-500 rounded-lg shadow-lg"></div>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-8 lg:p-12">

                <h2 class="text-3xl font-bold text-red-600 text-center mb-10">Masuk</h2>

                <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                            </span>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Email"
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </span>
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Password"
                                required
                                class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                            <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500"
                            />
                            <span class="ml-2 text-gray-600">Ingat saya</span>
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-red-600 hover:underline font-medium"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 rounded-full shadow-lg transition transform hover:scale-[1.02] active:scale-95 disabled:opacity-50"
                    >
                        Masuk
                    </button>

                    <p class="text-center text-sm text-gray-600 mt-8">
                        Belum punya akun?
                        <Link :href="route('register')" class="text-red-600 font-bold hover:underline">
                            Daftar Sekarang
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Menghilangkan ring biru default pada input yang sudah kita override dengan ring kuning */
input:focus {
    outline: none;
}
</style>
