<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch } from '../api-client';
import { useAuthStore } from '../stores/auth-store';
import { buildQuery } from '../composables/query-string';

const emit = defineEmits(['close', 'select']);

const auth = useAuthStore();
const files = ref([]);
const loading = ref(false);
const uploading = ref(false);
const search = ref('');
const error = ref('');
const fileInputRef = ref(null);

let searchTimer = null;

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const qs = buildQuery({ search: search.value, per_page: 60 });
        const res = await apiFetch(`/media${qs}`, {}, auth.token);
        files.value = res.data || [];
    } catch (e) {
        error.value = e.data?.message || 'Không tải được thư viện ảnh.';
    } finally {
        loading.value = false;
    }
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 350);
}

function pick(file) {
    emit('select', file);
}

function openFileDialog() {
    fileInputRef.value?.click();
}

async function onUpload(e) {
    const selected = Array.from(e.target.files || []);
    if (!selected.length) return;

    uploading.value = true;
    error.value = '';

    try {
        const fd = new FormData();
        selected.forEach((f) => fd.append('files[]', f));

        const res = await apiFetch('/media/upload', { method: 'POST', body: fd }, auth.token);
        const uploaded = res.data || [];
        await load();

        // Bấm "Tải lên" trong bộ chọn ảnh là đã có ý dùng ảnh đó ngay.
        if (uploaded[0]) pick(uploaded[0]);
    } catch (e) {
        error.value = e.data?.message || 'Không tải được ảnh lên.';
    } finally {
        uploading.value = false;
        if (fileInputRef.value) fileInputRef.value.value = '';
    }
}

onMounted(load);
</script>

<template>
    <div class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4" @click.self="$emit('close')">
        <div class="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-800">Chọn ảnh từ thư viện</h2>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Đóng"
                    @click="$emit('close')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-3">
                <div class="relative flex-1">
                    <svg class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm tên ảnh..."
                        class="w-full rounded border border-gray-300 py-2 pr-3 pl-9 text-sm"
                        @input="onSearchInput"
                    />
                </div>
                <button
                    type="button"
                    :disabled="uploading"
                    class="flex items-center gap-1.5 rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="openFileDialog"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 9l5-5 5 5M12 4v12" />
                    </svg>
                    {{ uploading ? 'Đang tải...' : 'Tải lên' }}
                </button>
                <input ref="fileInputRef" type="file" accept="image/*" multiple class="hidden" @change="onUpload" />
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

                <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Đang tải...</div>
                <div v-else-if="!files.length" class="flex flex-col items-center gap-2 py-10 text-gray-400">
                    <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm">Thư viện chưa có ảnh nào.</p>
                    <p class="text-xs">Bấm "Tải lên" để thêm ảnh.</p>
                </div>

                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5">
                    <button
                        v-for="f in files"
                        :key="f.id"
                        type="button"
                        class="group overflow-hidden rounded border border-gray-200 text-left hover:border-violet-400"
                        @click="pick(f)"
                    >
                        <img :src="f.thumb_url || f.url" class="aspect-square w-full object-cover" />
                        <div class="truncate px-2 py-1 text-xs text-gray-600 group-hover:text-violet-700">{{ f.name }}</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
