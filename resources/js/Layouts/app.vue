<script setup>
import { Head } from '@inertiajs/vue3';
import Navbar from '@/Components/navbar.vue';
import Footer from '@/Components/footer.vue';
</script>

<template>
  <div class="min-h-screen flex flex-col bg-bg text-text">

    <Head title="HiWash" />

    <header>
      <Navbar :canLogin="$page.props.canLogin" :canRegister="$page.props.canRegister" />
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <Footer />

  </div>
</template>
