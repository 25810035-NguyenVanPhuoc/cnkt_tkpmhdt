<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';

const auth = useAuthStore();

const roles = ref([]);
const catalog = ref([]);
const loading = ref(false);
const listError = ref('');

const showForm = ref(false);
const editingId = ref(null);
const editingName = ref('');
const formError = ref('');
const saving = ref(false);

const form = reactive({ name: '', permissions: [] });

const MODULE_LABEL = {
    sales: 'Bán hàng',
    products: 'Sản phẩm',
    categories: 'Danh mục',
    warehouse: 'Kho hàng',
    purchasing: 'Nhập hàng / NCC',
    customers: 'Khách hàng',
    employees: 'Nhân viên',
    promotions: 'Khuyến mãi',
    roles: 'Vai trò',
    settings: 'Cài đặt',
};

function moduleLabel(module) {
    return MODULE_LABEL[module] || module;
}

const isEditingAdmin = computed(() => editingName.value === 'admin');

async function loadRoles() {
    loading.value = true;
    listError.value = '';

    try {
        const res = await apiFetch('/roles', {}, auth.token);
        roles.value = res.data || [];
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách vai trò.';
    } finally {
        loading.value = false;
    }
}

async function loadCatalog() {
    try {
        const res = await apiFetch('/permissions', {}, auth.token);
        catalog.value = res.data || [];
    } catch (e) {
        catalog.value = [];
    }
}

function isModuleFullyChecked(group) {
    return group.permissions.every((p) => form.permissions.includes(p));
}

function toggleModule(group) {
    if (isModuleFullyChecked(group)) {
        form.permissions = form.permissions.filter((p) => !group.permissions.includes(p));
    } else {
        const merged = new Set([...form.permissions, ...group.permissions]);
        form.permissions = Array.from(merged);
    }
}

function openCreate() {
    editingId.value = null;
    editingName.value = '';
    Object.assign(form, { name: '', permissions: [] });
    formError.value = '';
    showForm.value = true;
}

function openEdit(role) {
    editingId.value = role.id;
    editingName.value = role.name;
    Object.assign(form, { name: role.name, permissions: [...(role.permissions || [])] });
    formError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
}

async function submitForm() {
    saving.value = true;
    formError.value = '';

    try {
        if (editingId.value) {
            const payload = { permissions: form.permissions };
            if (!isEditingAdmin.value) payload.name = form.name;

            await apiFetch(
                `/roles/${editingId.value}`,
                { method: 'PUT', body: JSON.stringify(payload) },
                auth.token,
            );
        } else {
            await apiFetch(
                '/roles',
                { method: 'POST', body: JSON.stringify({ name: form.name, permissions: form.permissions }) },
                auth.token,
            );
        }

        showForm.value = false;
        await loadRoles();
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được vai trò.';
    } finally {
        saving.value = false;
    }
}

async function deleteRole(role) {
    if (!confirm(`Xoá vai trò "${role.name}"?`)) return;

    listError.value = '';
    try {
        await apiFetch(`/roles/${role.id}`, { method: 'DELETE' }, auth.token);
        await loadRoles();
    } catch (e) {
        listError.value = e.data?.message || 'Không xoá được vai trò.';
    }
}

onMounted(async () => {
    await loadCatalog();
    await loadRoles();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Phân quyền</h1>
            <button
                class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                @click="openCreate"
            >
                + Thêm vai trò
            </button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Tên vai trò</th>
                        <th class="px-4 py-2">Số quyền</th>
                        <th class="px-4 py-2">Số nhân viên</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!roles.length">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-400">Chưa có vai trò nào.</td>
                    </tr>
                    <tr v-for="r in roles" :key="r.id" class="border-b border-gray-100">
                        <td class="px-4 py-2 text-gray-800">{{ r.name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ r.permissions?.length || 0 }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ r.employees_count }}</td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    title="Sửa"
                                    @click="openEdit(r)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="r.name !== 'admin'"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700"
                                    title="Xoá"
                                    @click="deleteRole(r)"
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

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form
                class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
                @submit.prevent="submitForm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ editingId ? 'Sửa vai trò' : 'Thêm vai trò' }}
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

                <div class="mb-4">
                    <label class="mb-1 block text-sm text-gray-600">Tên vai trò</label>
                    <input
                        v-model="form.name"
                        required
                        :disabled="isEditingAdmin"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50 disabled:text-gray-400"
                    />
                    <p v-if="isEditingAdmin" class="mt-1 text-xs text-gray-400">Không thể đổi tên vai trò quản trị.</p>
                </div>

                <label class="mb-2 block text-sm text-gray-600">Quyền hạn</label>
                <div class="space-y-3 rounded border border-gray-200 p-3">
                    <div v-for="group in catalog" :key="group.module" class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                        <label class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input
                                type="checkbox"
                                :checked="isModuleFullyChecked(group)"
                                @change="toggleModule(group)"
                            />
                            {{ moduleLabel(group.module) }}
                        </label>
                        <div class="ml-6 flex flex-wrap gap-3">
                            <label v-for="perm in group.permissions" :key="perm" class="flex items-center gap-1.5 text-sm text-gray-600">
                                <input type="checkbox" :value="perm" v-model="form.permissions" />
                                {{ perm.split('.')[1] }}
                            </label>
                        </div>
                    </div>
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
