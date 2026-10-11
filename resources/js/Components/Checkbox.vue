<script setup>
defineProps({
    checked: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:checked']);
</script>

<template>
    <input
        type="checkbox"
        :checked="checked"
        @change="$emit('update:checked', $event.target.checked)"
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
    />
</template>
