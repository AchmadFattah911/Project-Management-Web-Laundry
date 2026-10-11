<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    password: '',
    password_confirmation: '',
    role: 'pelanggan',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Daftar - Hi Wash Laundry" />

    <div class="flex min-h-screen bg-gray-100 font-sans">
        <!-- Left Side (Branding) -->
        <div class="hidden lg:flex w-1/2 bg-red-600 flex-col items-center justify-center p-12 text-white relative overflow-hidden">
            <div class="absolute top-0 left-0 w-64 h-64 bg-red-500 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl opacity-50"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 bg-red-700 rounded-full translate-x-1/3 translate-y-1/3 blur-3xl opacity-40"></div>

            <div class="relative z-10 text-center">
                <!-- Logo -->
                <div class="flex items-center justify-center mb-6">
                    <div class="flex items-center space-x-2">
                        <svg class="w-12 h-12 fill-white" viewBox="0 0 24 24">
                            <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
                        </svg>
                        <span class="text-3xl font-bold italic tracking-tighter">
                            Hi Wash <span class="font-light not-italic">Laundry</span>
                        </span>
                    </div>
                </div>

                <p class="text-red-100 text-lg max-w-xs leading-relaxed mb-8">
                    Bergabunglah dan nikmati kemudahan layanan laundry profesional kami.
                </p>

                <!-- Illustration -->
                <div class="mt-4 relative inline-block">
                    <div class="w-64 h-72 bg-white rounded-2xl shadow-2xl relative border-8 border-gray-100 overflow-hidden">
                        <div class="h-1/4 border-b-4 border-gray-100 flex items-center justify-around px-4">
                            <div class="w-12 h-2 bg-gray-200 rounded"></div>
                            <div class="w-4 h-4 bg-gray-200 rounded-full"></div>
                        </div>
                        <div class="flex justify-center mt-6">
                            <div class="w-36 h-36 rounded-full border-8 border-gray-100 flex items-end justify-center overflow-hidden bg-blue-50">
                                <div class="w-full h-1/2 bg-blue-400 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="flex justify-center mt-3 space-x-2">
                            <div class="w-20 h-2 bg-gray-200 rounded"></div>
                            <div class="w-10 h-2 bg-gray-300 rounded"></div>
                        </div>
                    </div>
                    <div class="absolute -bottom-4 -left-8 w-28 h-16 bg-yellow-400 rounded-lg shadow-lg"></div>
                    <div class="absolute -bottom-4 -right-8 w-28 h-16 bg-blue-500 rounded-lg shadow-lg"></div>
                </div>

                <!-- Steps info -->
                <ul class="mt-14 space-y-2 text-left text-sm text-red-100">
                    <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">1</span> Daftar akun gratis</li>
                    <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">2</span> Pesan layanan laundry</li>
                    <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">3</span> Pakaian bersih diantar ke rumah</li>
                </ul>
            </div>
        </div>

        <!-- Right Side (Form) -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 overflow-y-auto">
            <div class="w-full max-w-md bg-white rounded-3xl shadow-xl p-8 lg:p-10 my-6">

                <h2 class="text-3xl font-bold text-red-600 text-center mb-8">Daftar Akun</h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Nama -->
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </span>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Nama Lengkap"
                                required
                                autofocus
                                autocomplete="name"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </span>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Email"
                                required
                                autocomplete="username"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <!-- Nomor HP -->
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </span>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="Nomor HP (opsional)"
                                autocomplete="tel"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>

                    <!-- Alamat -->
                    <div>
                        <div class="relative">
                            <span class="absolute top-3 left-0 flex items-start pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </span>
                            <textarea
                                v-model="form.address"
                                placeholder="Alamat (opsional)"
                                rows="2"
                                autocomplete="street-address"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition resize-none"
                            ></textarea>
                        </div>
                        <p v-if="form.errors.address" class="mt-1 text-xs text-red-500">{{ form.errors.address }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Password"
                                required
                                autocomplete="new-password"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Konfirmasi Password -->
                    <div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </span>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Konfirmasi Password"
                                required
                                autocomplete="new-password"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 outline-none transition"
                            />
                        </div>
                        <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <!-- Role (hidden, auto pelanggan) -->
                    <input type="hidden" v-model="form.role" />

                    <!-- Role Badge Info -->
                    <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-2 text-sm text-yellow-700">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Akun kamu akan terdaftar sebagai <strong class="ml-1">Pelanggan</strong>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 rounded-full shadow-lg transition transform hover:scale-[1.02] active:scale-95 disabled:opacity-50 mt-2"
                    >
                        Daftar Sekarang
                    </button>

                    <p class="text-center text-sm text-gray-600">
                        Sudah punya akun?
                        <Link :href="route('login')" class="text-red-600 font-bold hover:underline">
                            Masuk
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:focus,
textarea:focus {
    outline: none;
}
</style>
