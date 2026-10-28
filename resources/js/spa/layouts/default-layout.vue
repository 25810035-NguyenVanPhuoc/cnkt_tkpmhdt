<script setup>
import { computed } from 'vue';
import { useSidebarProvider } from '../composables/sidebar';
import Sidebar from '../components/Sidebar.vue';
import AppHeader from '../components/AppHeader.vue';

const { isExpanded } = useSidebarProvider();

const contentMargin = computed(() => (isExpanded.value ? 'lg:ml-64' : 'lg:ml-18'));
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Sidebar />
        <div :class="['flex flex-col transition-all duration-300 ease-in-out', contentMargin]">
            <AppHeader />
            <main class="flex-1 p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>
