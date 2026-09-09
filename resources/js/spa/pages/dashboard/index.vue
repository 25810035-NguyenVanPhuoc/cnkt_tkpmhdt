<script setup>
import { useAuthStore } from '../../stores/auth-store';

const auth = useAuthStore();
</script>

<template>
    <div>
        <h1 class="text-xl font-semibold text-gray-800">
            Chào {{ auth.user?.name }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Tổng quan hệ thống Quản lý Bán hàng Thiết bị Điện tử.
        </p>
    </div>
</template>
