<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';

const auth = useAuthStore();

const FIELDS = [
    { key: 'store_name', label: 'Tên cửa hàng' },
    { key: 'store_phone', label: 'Số điện thoại' },
    { key: 'store_email', label: 'Email' },
    { key: 'store_address', label: 'Địa chỉ' },
    { key: 'tax_rate', label: 'Thuế VAT (%)' },
];

const form = reactive(Object.fromEntries(FIELDS.map((f) => [f.key, ''])));
const loading = ref(false);
const saving = ref(false);
const pageError = ref('');
const savedMessage = ref('');

async function loadSettings() {
    loading.value = true;
    pageError.value = '';
    try {
        const res = await apiFetch('/settings', {}, auth.token);
        const data = res.data || {};
        FIELDS.forEach((f) => {
            form[f.key] = data[f.key] ?? '';
        });
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được cấu hình hệ thống.';
    } finally {
        loading.value = false;
    }
}

async function saveSettings() {
    saving.value = true;
    pageError.value = '';
    savedMessage.value = '';
    try {
        const payload = { settings: FIELDS.map((f) => ({ key: f.key, value: form[f.key] || null })) };
        await apiFetch('/settings', { method: 'PUT', body: JSON.stringify(payload) }, auth.token);
        savedMessage.value = 'Đã lưu cấu hình.';
    } catch (e) {
        pageError.value = e.data?.message || 'Không lưu được cấu hình.';
    } finally {
        saving.value = false;
    }
}

onMounted(loadSettings);
</script>

<template>
    <div>
        <h1 class="mb-4 text-lg font-semibold text-gray-800">Cài đặt hệ thống</h1>

        <form class="max-w-xl rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="saveSettings">
            <h2 class="mb-3 text-sm font-semibold text-gray-700">Thông tin cửa hàng</h2>

            <div v-if="loading" class="text-sm text-gray-500">Đang tải...</div>

            <template v-else>
                <div v-for="f in FIELDS" :key="f.key" class="mb-3">
                    <label class="mb-1 block text-sm text-gray-600">{{ f.label }}</label>
                    <input v-model="form[f.key]" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </div>
            </template>

            <p v-if="pageError" class="mb-3 text-sm text-red-600">{{ pageError }}</p>
            <p v-if="savedMessage" class="mb-3 text-sm text-green-600">{{ savedMessage }}</p>

            <div class="flex justify-end">
                <button type="submit" :disabled="saving || loading" class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
                </button>
            </div>
        </form>
    </div>
</template>
