<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';
import { slugify } from '../../../composables/format';
import { buildQuery } from '../../../composables/query-string';

const auth = useAuthStore();

const categories = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');

const filters = reactive({ q: '', is_active: '' });

const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({ name: '', slug: '', is_active: true });
const form = reactive(emptyForm());
let slugTouched = false;

async function loadCategories(page = 1) {
    loading.value = true;
    listError.value = '';

    try {
        const qs = buildQuery({ ...filters, page });
        const res = await apiFetch(`/categories${qs}`, {}, auth.token);
        categories.value = res.data || [];
        meta.value = res.meta || null;
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách danh mục.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    slugTouched = false;
    formError.value = '';
    showForm.value = true;
}

function openEdit(category) {
    editingId.value = category.id;
    Object.assign(form, {
        name: category.name,
        slug: category.slug,
        is_active: category.is_active,
    });
    slugTouched = true;
    formError.value = '';
    showForm.value = true;
}

function onNameInput() {
    if (!slugTouched) {
        form.slug = slugify(form.name);
    }
}

function onSlugInput() {
    slugTouched = true;
}

function closeForm() {
    showForm.value = false;
}

async function submitForm() {
    saving.value = true;
    formError.value = '';

    const payload = {
        name: form.name,
        slug: form.slug,
        is_active: form.is_active,
    };

    try {
        if (editingId.value) {
            await apiFetch(
                `/categories/${editingId.value}`,
                { method: 'PUT', body: JSON.stringify(payload) },
                auth.token,
            );
        } else {
            await apiFetch('/categories', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        }

        showForm.value = false;
        await loadCategories(meta.value?.current_page || 1);
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được danh mục.';
    } finally {
        saving.value = false;
    }
}

async function deleteCategory(category) {
    if (!confirm(`Xoá danh mục "${category.name}"?`)) return;

    listError.value = '';
    try {
        await apiFetch(`/categories/${category.id}`, { method: 'DELETE' }, auth.token);
        await loadCategories(meta.value?.current_page || 1);
    } catch (e) {
        listError.value = e.data?.message || 'Không xoá được danh mục.';
    }
}

onMounted(() => {
    loadCategories();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Danh mục</h1>
            <button
                class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                @click="openCreate"
            >
                + Thêm danh mục
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="filters.q"
                type="text"
                placeholder="Tìm theo tên..."
                class="rounded border border-gray-300 px-3 py-2 text-sm"
                @keyup.enter="loadCategories()"
            />
            <select v-model="filters.is_active" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadCategories()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang hiển thị</option>
                <option value="0">Đã ẩn</option>
            </select>
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="loadCategories()">
                Lọc
            </button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Tên danh mục</th>
                        <th class="px-4 py-2">Slug</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!categories.length">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-400">Chưa có danh mục nào.</td>
                    </tr>
                    <tr v-for="c in categories" :key="c.id" class="border-b border-gray-100">
                        <td class="px-4 py-2 text-gray-800">{{ c.name }}</td>
                        <td class="px-4 py-2 font-mono text-gray-500">{{ c.slug }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded px-2 py-0.5 text-xs"
                                :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ c.is_active ? 'Đang hiển thị' : 'Đã ẩn' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    title="Sửa"
                                    @click="openEdit(c)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700"
                                    title="Xoá"
                                    @click="deleteCategory(c)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-sm">
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="meta.current_page <= 1"
                @click="loadCategories(meta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="loadCategories(meta.current_page + 1)"
            >
                Sau →
            </button>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitForm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ editingId ? 'Sửa danh mục' : 'Thêm danh mục' }}
                    </h2>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Đóng"
                        @click="closeForm"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Tên danh mục</label>
                        <input
                            v-model="form.name"
                            required
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            @input="onNameInput"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Slug</label>
                        <input
                            v-model="form.slug"
                            required
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                            @input="onSlugInput"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.is_active" type="checkbox" />
                        Đang hiển thị
                    </label>
                </div>

                <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeForm">
                        Hủy
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
