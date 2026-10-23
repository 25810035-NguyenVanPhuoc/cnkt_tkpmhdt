<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../api-client';
import { useAuthStore } from '../../stores/auth-store';
import { buildQuery } from '../../composables/query-string';

const auth = useAuthStore();

const customers = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');

const filters = reactive({ q: '' });

const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({ name: '', phone: '', email: '', address: '', loyalty_points: 0 });
const form = reactive(emptyForm());

const showDetail = ref(false);
const detailCustomer = ref(null);
const detailLoading = ref(false);
const detailError = ref('');

const STATUS_LABEL = {
    new: 'Mới',
    pending: 'Chờ xử lý',
    confirmed: 'Đã xác nhận',
    completed: 'Hoàn tất',
    cancelled: 'Đã huỷ',
};

function formatVnd(v) {
    return new Intl.NumberFormat('vi-VN').format(v || 0) + ' đ';
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('vi-VN');
}

async function openDetail(customer) {
    showDetail.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detailCustomer.value = null;
    try {
        const res = await apiFetch(`/customers/${customer.id}`, {}, auth.token);
        detailCustomer.value = res.data;
    } catch (e) {
        detailError.value = e.data?.message || 'Không tải được chi tiết khách hàng.';
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    showDetail.value = false;
    detailCustomer.value = null;
}

async function loadCustomers(page = 1) {
    loading.value = true;
    listError.value = '';

    try {
        const qs = buildQuery({ ...filters, page });
        const res = await apiFetch(`/customers${qs}`, {}, auth.token);
        customers.value = res.data || [];
        meta.value = res.meta || null;
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách khách hàng.';
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

function openEdit(customer) {
    editingId.value = customer.id;
    Object.assign(form, {
        name: customer.name,
        phone: customer.phone,
        email: customer.email || '',
        address: customer.address || '',
        loyalty_points: customer.loyalty_points,
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
        phone: form.phone,
        email: form.email || null,
        address: form.address || null,
        loyalty_points: Number(form.loyalty_points || 0),
    };

    try {
        if (editingId.value) {
            await apiFetch(`/customers/${editingId.value}`, { method: 'PUT', body: JSON.stringify(payload) }, auth.token);
        } else {
            await apiFetch('/customers', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        }

        showForm.value = false;
        await loadCustomers(meta.value?.current_page || 1);
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được khách hàng.';
    } finally {
        saving.value = false;
    }
}

async function deleteCustomer(customer) {
    if (!confirm(`Xoá khách hàng "${customer.name}"?`)) return;

    listError.value = '';
    try {
        await apiFetch(`/customers/${customer.id}`, { method: 'DELETE' }, auth.token);
        await loadCustomers(meta.value?.current_page || 1);
    } catch (e) {
        listError.value = e.data?.message || 'Không xoá được khách hàng.';
    }
}

onMounted(() => {
    loadCustomers();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Khách hàng</h1>
            <button
                class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                @click="openCreate"
            >
                + Thêm khách hàng
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="filters.q"
                type="text"
                placeholder="Tìm theo tên hoặc SĐT..."
                class="rounded border border-gray-300 px-3 py-2 text-sm"
                @keyup.enter="loadCustomers()"
            />
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="loadCustomers()">
                Lọc
            </button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Tên khách hàng</th>
                        <th class="px-4 py-2">SĐT</th>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2">Điểm tích luỹ</th>
                        <th class="px-4 py-2">Số đơn</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!customers.length">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">Chưa có khách hàng nào.</td>
                    </tr>
                    <tr v-for="c in customers" :key="c.id" class="border-b border-gray-100">
                        <td class="px-4 py-2 text-gray-800">{{ c.name }}</td>
                        <td class="px-4 py-2 font-mono text-gray-600">{{ c.phone }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ c.email || '—' }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ c.loyalty_points }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ c.orders_count ?? 0 }}</td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    title="Xem chi tiết"
                                    @click="openDetail(c)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </button>
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
                                    @click="deleteCustomer(c)"
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
                @click="loadCustomers(meta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="loadCustomers(meta.current_page + 1)"
            >
                Sau →
            </button>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitForm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ editingId ? 'Sửa khách hàng' : 'Thêm khách hàng' }}
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
                        <label class="mb-1 block text-sm text-gray-600">Tên khách hàng</label>
                        <input v-model="form.name" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Số điện thoại</label>
                        <input v-model="form.phone" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Địa chỉ</label>
                        <input v-model="form.address" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Điểm tích luỹ</label>
                        <input v-model.number="form.loyalty_points" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
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

        <div v-if="showDetail" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Chi tiết khách hàng</h2>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Đóng"
                        @click="closeDetail"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="detailLoading" class="py-6 text-center text-gray-400">Đang tải...</div>
                <p v-else-if="detailError" class="text-sm text-red-600">{{ detailError }}</p>
                <div v-else-if="detailCustomer">
                    <div class="mb-4 rounded border border-gray-200 bg-gray-50 p-3 text-sm">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <div><span class="text-gray-500">Tên khách hàng:</span> <span class="font-medium text-gray-800">{{ detailCustomer.name }}</span></div>
                            <div><span class="text-gray-500">SĐT:</span> <span class="font-mono">{{ detailCustomer.phone }}</span></div>
                            <div><span class="text-gray-500">Email:</span> {{ detailCustomer.email || '—' }}</div>
                            <div><span class="text-gray-500">Điểm tích luỹ:</span> {{ detailCustomer.loyalty_points }}</div>
                            <div class="col-span-2"><span class="text-gray-500">Địa chỉ:</span> {{ detailCustomer.address || '—' }}</div>
                            <div><span class="text-gray-500">Tổng số đơn:</span> {{ detailCustomer.orders_count ?? 0 }}</div>
                            <div><span class="text-gray-500">Khách hàng từ:</span> {{ formatDateTime(detailCustomer.created_at) }}</div>
                        </div>
                    </div>

                    <div class="mb-2 text-sm font-medium text-gray-700">
                        Lịch sử mua hàng (gần nhất {{ detailCustomer.orders?.length || 0 }} đơn)
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th class="px-3 py-2">Mã đơn</th>
                                    <th class="px-3 py-2">Kho</th>
                                    <th class="px-3 py-2">Trạng thái</th>
                                    <th class="px-3 py-2">Tổng tiền</th>
                                    <th class="px-3 py-2">Ngày đặt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!detailCustomer.orders?.length">
                                    <td colspan="5" class="px-3 py-4 text-center text-gray-400">Khách hàng chưa có đơn hàng nào.</td>
                                </tr>
                                <tr v-for="o in detailCustomer.orders" :key="o.id" class="border-b border-gray-100">
                                    <td class="px-3 py-2 font-mono text-gray-700">{{ o.code }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ o.warehouse?.name || '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ STATUS_LABEL[o.status] || o.status }}</span>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ formatVnd(o.grand_total) }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ formatDateTime(o.order_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeDetail">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
