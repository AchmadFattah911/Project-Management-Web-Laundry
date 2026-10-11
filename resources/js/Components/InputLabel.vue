<script setup>
defineProps({
    for: {
        type: String,
        default: '',
    },
    value: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <label :for="$props.for" class="block text-sm font-medium text-gray-700">
        {{ value }} <slot />
    </label>
</template>
