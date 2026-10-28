<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';

const auth = useAuthStore();

const lines = ref([]);
const loading = ref(false);
const pageError = ref('');

const EVENT_BADGE = {
    LOGIN_SUCCESS: 'bg-green-100 text-green-700',
    LOGIN_FAILED: 'bg-red-100 text-red-700',
    ORDER_CREATED: 'bg-blue-100 text-blue-700',
};

function parseLine(line) {
    const match = line.match(/^\[(.+?)\]\s+(\S+)\s*(.*)$/);
    if (!match) return { time: '', event: '', rest: line };
    return { time: match[1], event: match[2], rest: match[3] };
}

async function load() {
    loading.value = true;
    pageError.value = '';
    try {
        const res = await apiFetch('/activity-log?lines=300', {}, auth.token);
        lines.value = res.data || [];
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được nhật ký hoạt động.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-800">Nhật ký hoạt động</h1>
                <p class="mt-1 text-xs text-gray-500">
                    Ghi lại sự kiện đăng nhập và mua hàng, đọc trực tiếp từ file
                    <code class="rounded bg-gray-100 px-1 py-0.5">storage/logs/activity.log</code>. Mới nhất ở trên cùng.
                </p>
            </div>
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" :disabled="loading" @click="load">
                {{ loading ? 'Đang tải...' : 'Làm mới' }}
            </button>
        </div>

        <p v-if="pageError" class="mb-3 text-sm text-red-600">{{ pageError }}</p>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div v-if="!loading && !lines.length" class="px-4 py-6 text-center text-sm text-gray-400">
                Chưa có hoạt động nào được ghi lại.
            </div>
            <ul v-else class="divide-y divide-gray-100">
                <li v-for="(line, index) in lines" :key="index" class="flex items-start gap-3 px-4 py-2 text-sm">
                    <span class="shrink-0 font-mono text-xs text-gray-400">{{ parseLine(line).time }}</span>
                    <span
                        class="shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                        :class="EVENT_BADGE[parseLine(line).event] || 'bg-gray-100 text-gray-600'"
                    >
                        {{ parseLine(line).event }}
                    </span>
                    <span class="font-mono text-xs text-gray-600">{{ parseLine(line).rest }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
