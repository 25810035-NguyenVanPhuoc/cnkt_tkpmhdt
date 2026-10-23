<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiFetch } from '../../api-client';
import { useAuthStore } from '../../stores/auth-store';

const auth = useAuthStore();
const router = useRouter();

const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const loading = ref(false);

async function handleSubmit() {
    error.value = '';
    loading.value = true;

    try {
        await apiFetch(
            '/change-password',
            {
                method: 'POST',
                body: JSON.stringify({
                    password: password.value,
                    password_confirmation: passwordConfirmation.value,
                }),
            },
            auth.token,
        );

        if (auth.user) {
            auth.user.must_change_password = false;
        }

        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e.data?.message || 'Không đổi được mật khẩu.';
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
            <h1 class="mb-2 text-lg font-semibold text-gray-800">Đổi mật khẩu</h1>
            <p class="mb-4 text-sm text-gray-500">
                Đây là lần đăng nhập đầu tiên (hoặc mật khẩu của bạn vừa được đặt lại). Vui lòng đặt mật khẩu mới trước khi tiếp tục.
            </p>

            <label class="mb-1 block text-sm text-gray-600">Mật khẩu mới</label>
            <input
                v-model="password"
                type="password"
                required
                minlength="6"
                class="mb-3 w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />

            <label class="mb-1 block text-sm text-gray-600">Nhập lại mật khẩu mới</label>
            <input
                v-model="passwordConfirmation"
                type="password"
                required
                minlength="6"
                class="mb-3 w-full rounded border border-gray-300 px-3 py-2 text-sm"
            />

            <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

            <button
                type="submit"
                :disabled="loading"
                class="w-full rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                {{ loading ? 'Đang lưu...' : 'Đổi mật khẩu' }}
            </button>
        </form>
    </div>
</template>
