<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth-store';
import { navConfig } from '../router/nav-config';

const auth = useAuthStore();
const router = useRouter();

const visibleNav = navConfig.filter((item) => auth.isModulePermitted(item.module));

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <aside class="w-60 shrink-0 border-r border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-4 py-4 text-sm font-semibold text-gray-800">
                Quản lý Bán hàng
            </div>
            <nav class="p-2">
                <router-link
                    v-for="item in visibleNav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="block rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-100"
                    active-class="bg-gray-100 font-medium text-gray-900"
                >
                    {{ item.label }}
                </router-link>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
                <span class="text-sm text-gray-500">{{ auth.user?.name }}</span>
                <button class="text-sm text-gray-500 hover:text-gray-800" @click="handleLogout">
                    Đăng xuất
                </button>
            </header>

            <main class="flex-1 p-6">
                <router-view />
            </main>
        </div>
    </div>
</template>
