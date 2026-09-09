<script setup>
defineProps({
    title: { type: String, required: true },
    note: { type: String, default: 'Module này sẽ được xây dựng ở milestone tiếp theo.' },
});
</script>

<template>
    <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center">
        <h1 class="text-xl font-semibold text-gray-800">{{ title }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ note }}</p>
    </div>
</template>
