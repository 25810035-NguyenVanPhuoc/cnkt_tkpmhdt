<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../api-client';
import { useAuthStore } from '../../stores/auth-store';
import { buildQuery } from '../../composables/query-string';

const auth = useAuthStore();

const employees = ref([]);
const roles = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');

const filters = reactive({ q: '', role_id: '', is_active: '' });

const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    phone: '',
    employee_code: '',
    role_id: '',
    is_active: true,
});

const form = reactive(emptyForm());

function roleName(id) {
    return roles.value.find((r) => r.id === id)?.name || '—';
}

const ROLE_LABEL = {
    admin: 'Quản trị viên',
    sales_staff: 'NV bán hàng',
    warehouse_staff: 'NV kho',
};

function roleLabel(name) {
    return ROLE_LABEL[name] || name;
}

async function loadRoles() {
    try {
        const res = await apiFetch('/roles', {}, auth.token);
        roles.value = res.data || [];
    } catch (e) {
        roles.value = [];
    }
}

async function loadEmployees(page = 1) {
    loading.value = true;
    listError.value = '';

    try {
        const qs = buildQuery({ ...filters, page });
        const res = await apiFetch(`/employees${qs}`, {}, auth.token);
        employees.value = res.data || [];
        meta.value = res.meta || null;
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách nhân viên.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    formError.value = '';
    showForm.value = true;
}

function openEdit(employee) {
    editingId.value = employee.id;
    Object.assign(form, {
        name: employee.name,
        email: employee.email,
        password: '',
        phone: employee.phone || '',
        employee_code: employee.employee_code || '',
        role_id: employee.role?.id || '',
        is_active: employee.is_active,
    });
    formError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
}

async function submitForm() {
    saving.value = true;
    formError.value = '';

    const payload = {
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        employee_code: form.employee_code || null,
        role_id: Number(form.role_id),
        is_active: form.is_active,
    };

    if (form.password) {
        payload.password = form.password;
    }

    try {
        if (editingId.value) {
            await apiFetch(
                `/employees/${editingId.value}`,
                { method: 'PUT', body: JSON.stringify(payload) },
                auth.token,
            );
        } else {
            await apiFetch('/employees', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        }

        showForm.value = false;
        await loadEmployees(meta.value?.current_page || 1);
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được nhân viên.';
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(employee) {
    const nextActive = !employee.is_active;
    const verb = nextActive ? 'Mở khoá' : 'Khoá';
    if (!confirm(`${verb} tài khoản "${employee.name}"?`)) return;

    listError.value = '';
    try {
        await apiFetch(
            `/employees/${employee.id}/status`,
            { method: 'PATCH', body: JSON.stringify({ is_active: nextActive }) },
            auth.token,
        );
        await loadEmployees(meta.value?.current_page || 1);
    } catch (e) {
        listError.value = e.data?.message || 'Không thực hiện được.';
    }
}

onMounted(async () => {
    await loadRoles();
    loadEmployees();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Nhân viên</h1>
            <button
                class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                @click="openCreate"
            >
                + Thêm nhân viên
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="filters.q"
                type="text"
                placeholder="Tìm theo tên/email/mã NV..."
                class="rounded border border-gray-300 px-3 py-2 text-sm"
                @keyup.enter="loadEmployees()"
            />
            <select v-model="filters.role_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadEmployees()">
                <option value="">Tất cả vai trò</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r.name) }}</option>
            </select>
            <select v-model="filters.is_active" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadEmployees()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang hoạt động</option>
                <option value="0">Đã khoá</option>
            </select>
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="loadEmployees()">
                Lọc
            </button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Mã NV</th>
                        <th class="px-4 py-2">Tên</th>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2">SĐT</th>
                        <th class="px-4 py-2">Vai trò</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!employees.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Chưa có nhân viên nào.</td>
                    </tr>
                    <tr v-for="emp in employees" :key="emp.id" class="border-b border-gray-100">
                        <td class="px-4 py-2 font-mono text-gray-600">{{ emp.employee_code || '—' }}</td>
                        <td class="px-4 py-2 text-gray-800">{{ emp.name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ emp.email }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ emp.phone || '—' }}</td>
                        <td class="px-4 py-2">
                            <span v-if="emp.role" class="rounded bg-violet-50 px-2 py-0.5 text-xs text-violet-700">
                                {{ roleLabel(emp.role.name) }}
                            </span>
                            <span v-else class="text-xs text-gray-400">Chưa gán</span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded px-2 py-0.5 text-xs"
                                :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ emp.is_active ? 'Đang hoạt động' : 'Đã khoá' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    title="Sửa"
                                    @click="openEdit(emp)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="emp.id !== auth.user?.id"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-gray-100"
                                    :class="emp.is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'"
                                    :title="emp.is_active ? 'Khoá tài khoản' : 'Mở khoá tài khoản'"
                                    @click="toggleStatus(emp)"
                                >
                                    <svg v-if="emp.is_active" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm2-10V7a4 4 0 118 0" />
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
                @click="loadEmployees(meta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="loadEmployees(meta.current_page + 1)"
            >
                Sau →
            </button>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form
                class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg"
                @submit.prevent="submitForm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ editingId ? 'Sửa nhân viên' : 'Thêm nhân viên' }}
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

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Họ tên</label>
                        <input v-model="form.name" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Email</label>
                        <input v-model="form.email" type="email" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">
                            Mật khẩu {{ editingId ? '(để trống nếu không đổi)' : '' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!editingId"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">SĐT</label>
                        <input v-model="form.phone" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Mã nhân viên</label>
                        <input v-model="form.employee_code" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Vai trò</label>
                        <select v-model="form.role_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn vai trò --</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r.name) }}</option>
                        </select>
                    </div>
                    <label class="col-span-2 flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.is_active" type="checkbox" />
                        Đang hoạt động
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
