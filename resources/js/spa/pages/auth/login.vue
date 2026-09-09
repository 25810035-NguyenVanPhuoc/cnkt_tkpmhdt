<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth-store';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function handleSubmit() {
    error.value = '';
    loading.value = true;

    try {
        await auth.login(email.value, password.value);
        await auth.fetchMe();
        router.push(route.query.redirect || { name: 'dashboard' });
    } catch (e) {
        error.value = e.data?.message || 'Đăng nhập thất bại. Vui lòng kiểm tra lại thông tin.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-50">
        <form
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
            @submit.prevent="handleSubmit"
        >
            <h1 class="mb-4 text-lg font-semibold text-gray-800">Đăng nhập quản trị</h1>

            <label class="mb-1 block text-sm text-gray-600">Email</label>
            <input
                v-model="email"
                type="email"
                required
                class="mb-3 w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />

            <label class="mb-1 block text-sm text-gray-600">Mật khẩu</label>
            <input
                v-model="password"
                type="password"
                required
                class="mb-3 w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />

            <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                {{ loading ? 'Đang đăng nhập...' : 'Đăng nhập' }}
            </button>
        </form>
    </div>
</template>
