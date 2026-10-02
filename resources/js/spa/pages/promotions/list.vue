<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../api-client';
import { useAuthStore } from '../../stores/auth-store';
import { buildQuery } from '../../composables/query-string';

const auth = useAuthStore();

const TYPE_LABEL = {
    percentage: 'Giảm theo % ',
    fixed_amount: 'Giảm số tiền cố định',
    buy_x_get_y: 'Mua X tặng Y',
};
const APPLIES_TO_LABEL = {
    all: 'Toàn bộ đơn hàng',
    category: 'Theo danh mục',
    product: 'Theo sản phẩm',
};

const promotions = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');
const filters = reactive({ q: '' });

const categories = ref([]);
const products = ref([]);
const customers = ref([]);

async function loadLookups() {
    const [catRes, prodRes, custRes] = await Promise.allSettled([
        apiFetch('/categories?per_page=100', {}, auth.token),
        apiFetch('/products?per_page=200&is_active=1', {}, auth.token),
        apiFetch('/customers?per_page=200', {}, auth.token),
    ]);
    if (catRes.status === 'fulfilled') categories.value = catRes.value.data || [];
    if (prodRes.status === 'fulfilled') products.value = prodRes.value.data || [];
    if (custRes.status === 'fulfilled') customers.value = custRes.value.data || [];
}

async function loadPromotions(page = 1) {
    loading.value = true;
    listError.value = '';
    try {
        const qs = buildQuery({ ...filters, page });
        const res = await apiFetch(`/promotions${qs}`, {}, auth.token);
        promotions.value = res.data || [];
        meta.value = res.meta || null;
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách khuyến mãi.';
    } finally {
        loading.value = false;
    }
}

// ---------- form tạo/sửa ----------
const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({
    name: '', code: '', type: 'percentage', value: '', buy_quantity: '', get_quantity: '',
    min_order_amount: '', applies_to: 'all', target_id: '', starts_at: '', ends_at: '',
    usage_limit: '', is_active: true,
});
const form = reactive(emptyForm());

const targetOptions = computed(() => (form.applies_to === 'category' ? categories.value : products.value));

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    formError.value = '';
    showForm.value = true;
}

function openEdit(promo) {
    editingId.value = promo.id;
    Object.assign(form, {
        name: promo.name,
        code: promo.code || '',
        type: promo.type,
        value: promo.value ?? '',
        buy_quantity: promo.buy_quantity ?? '',
        get_quantity: promo.get_quantity ?? '',
        min_order_amount: promo.min_order_amount ?? '',
        applies_to: promo.applies_to,
        target_id: promo.target_id ?? '',
        starts_at: promo.starts_at ? promo.starts_at.slice(0, 16) : '',
        ends_at: promo.ends_at ? promo.ends_at.slice(0, 16) : '',
        usage_limit: promo.usage_limit ?? '',
        is_active: promo.is_active,
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
        code: form.code || null,
        type: form.type,
        value: form.value === '' ? null : Number(form.value),
        buy_quantity: form.buy_quantity === '' ? null : Number(form.buy_quantity),
        get_quantity: form.get_quantity === '' ? null : Number(form.get_quantity),
        min_order_amount: form.min_order_amount === '' ? null : Number(form.min_order_amount),
        applies_to: form.applies_to,
        target_id: form.target_id === '' ? null : Number(form.target_id),
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        usage_limit: form.usage_limit === '' ? null : Number(form.usage_limit),
        is_active: form.is_active,
    };

    try {
        if (editingId.value) {
            await apiFetch(`/promotions/${editingId.value}`, { method: 'PUT', body: JSON.stringify(payload) }, auth.token);
        } else {
            await apiFetch('/promotions', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        }
        showForm.value = false;
        await loadPromotions(meta.value?.current_page || 1);
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được khuyến mãi.';
    } finally {
        saving.value = false;
    }
}

async function deletePromotion(promo) {
    if (!confirm(`Xoá khuyến mãi "${promo.name}"?`)) return;
    try {
        await apiFetch(`/promotions/${promo.id}`, { method: 'DELETE' }, auth.token);
        await loadPromotions(meta.value?.current_page || 1);
    } catch (e) {
        listError.value = e.data?.message || 'Không xoá được khuyến mãi.';
    }
}

// ---------- gán khách hàng riêng ----------
const expandedId = ref(null);
const expandedDetail = ref(null);
const assignCustomerId = ref('');
const assignError = ref('');

async function toggleAssign(promo) {
    if (expandedId.value === promo.id) {
        expandedId.value = null;
        expandedDetail.value = null;
        return;
    }
    expandedId.value = promo.id;
    assignError.value = '';
    assignCustomerId.value = '';
    const res = await apiFetch(`/promotions/${promo.id}`, {}, auth.token);
    expandedDetail.value = res.data;
}

async function assignCustomer() {
    if (!assignCustomerId.value) return;
    assignError.value = '';
    try {
        const res = await apiFetch(
            `/promotions/${expandedDetail.value.id}/customers`,
            { method: 'POST', body: JSON.stringify({ customer_id: Number(assignCustomerId.value) }) },
            auth.token,
        );
        expandedDetail.value = res.data;
        assignCustomerId.value = '';
    } catch (e) {
        assignError.value = e.data?.message || 'Không gán được khách hàng.';
    }
}

async function unassignCustomer(customerId) {
    await apiFetch(`/promotions/${expandedDetail.value.id}/customers/${customerId}`, { method: 'DELETE' }, auth.token);
    const res = await apiFetch(`/promotions/${expandedDetail.value.id}`, {}, auth.token);
    expandedDetail.value = res.data;
}

onMounted(async () => {
    await loadLookups();
    loadPromotions();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Khuyến mãi</h1>
            <button class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700" @click="openCreate">
                + Thêm khuyến mãi
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="filters.q"
                type="text"
                placeholder="Tìm theo tên..."
                class="rounded border border-gray-300 px-3 py-2 text-sm"
                @keyup.enter="loadPromotions()"
            />
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="loadPromotions()">Lọc</button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Tên</th>
                        <th class="px-4 py-2">Mã</th>
                        <th class="px-4 py-2">Loại</th>
                        <th class="px-4 py-2">Phạm vi</th>
                        <th class="px-4 py-2">Đã dùng</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!promotions.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Chưa có khuyến mãi nào.</td>
                    </tr>
                    <template v-for="p in promotions" :key="p.id">
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-2 text-gray-800">{{ p.name }}</td>
                            <td class="px-4 py-2 font-mono text-gray-600">{{ p.code || '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ TYPE_LABEL[p.type] }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ APPLIES_TO_LABEL[p.applies_to] }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ p.usage_count }}<span v-if="p.usage_limit"> / {{ p.usage_limit }}</span></td>
                            <td class="px-4 py-2">
                                <span
                                    class="rounded px-2 py-0.5 text-xs"
                                    :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ p.is_active ? 'Đang chạy' : 'Tạm dừng' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100" @click="toggleAssign(p)">
                                        {{ expandedId === p.id ? 'Đóng' : 'KH riêng' }}
                                    </button>
                                    <button type="button" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100" @click="openEdit(p)">
                                        Sửa
                                    </button>
                                    <button type="button" class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="deletePromotion(p)">
                                        Xoá
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedId === p.id">
                            <td colspan="7" class="border-b border-gray-100 bg-gray-50 px-4 py-4">
                                <div v-if="expandedDetail">
                                    <p class="mb-2 text-xs text-gray-500">
                                        Khuyến mãi này mặc định dùng được cho mọi khách hàng có mã. Gán riêng cho khách hàng dưới đây
                                        để giới hạn chỉ những khách này mới dùng được.
                                    </p>
                                    <div class="mb-3 flex flex-wrap gap-2">
                                        <span
                                            v-for="c in expandedDetail.assigned_customers"
                                            :key="c.id"
                                            class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-700"
                                        >
                                            {{ c.name }} · {{ c.phone }}
                                            <button type="button" class="text-red-500 hover:text-red-700" @click="unassignCustomer(c.id)">×</button>
                                        </span>
                                        <span v-if="!expandedDetail.assigned_customers?.length" class="text-xs text-gray-400">
                                            Chưa gán khách hàng nào (đang mở cho mọi khách).
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <select v-model="assignCustomerId" class="rounded border border-gray-300 px-2 py-1.5 text-sm">
                                            <option value="" disabled>-- Chọn khách hàng --</option>
                                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} · {{ c.phone }}</option>
                                        </select>
                                        <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100" @click="assignCustomer">
                                            + Gán
                                        </button>
                                    </div>
                                    <p v-if="assignError" class="mt-2 text-sm text-red-600">{{ assignError }}</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-sm">
            <button class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="loadPromotions(meta.current_page - 1)">
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</span>
            <button class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="loadPromotions(meta.current_page + 1)">
                Sau →
            </button>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitForm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">{{ editingId ? 'Sửa khuyến mãi' : 'Thêm khuyến mãi' }}</h2>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="closeForm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Tên khuyến mãi</label>
                        <input v-model="form.name" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Mã (để trống nếu không cần nhập mã)</label>
                        <input v-model="form.code" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Loại khuyến mãi</label>
                        <select v-model="form.type" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="(label, value) in TYPE_LABEL" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>

                    <template v-if="form.type === 'percentage'">
                        <div class="col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Mức giảm (%)</label>
                            <input v-model.number="form.value" type="number" min="0" max="100" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                    </template>
                    <template v-else-if="form.type === 'fixed_amount'">
                        <div class="col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Số tiền giảm (đ)</label>
                            <input v-model.number="form.value" type="number" min="0" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                    </template>
                    <template v-else>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Mua (số lượng)</label>
                            <input v-model.number="form.buy_quantity" type="number" min="1" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Tặng (số lượng)</label>
                            <input v-model.number="form.get_quantity" type="number" min="1" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                    </template>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Phạm vi áp dụng</label>
                        <select v-model="form.applies_to" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="(label, value) in APPLIES_TO_LABEL" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="form.applies_to !== 'all'">
                        <label class="mb-1 block text-sm text-gray-600">{{ form.applies_to === 'category' ? 'Danh mục' : 'Sản phẩm' }}</label>
                        <select v-model="form.target_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn --</option>
                            <option v-for="t in targetOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Đơn tối thiểu (đ, tuỳ chọn)</label>
                        <input v-model.number="form.min_order_amount" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Giới hạn lượt dùng (tuỳ chọn)</label>
                        <input v-model.number="form.usage_limit" type="number" min="1" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Bắt đầu (tuỳ chọn)</label>
                        <input v-model="form.starts_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Kết thúc (tuỳ chọn)</label>
                        <input v-model="form.ends_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <label class="col-span-2 flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.is_active" type="checkbox" />
                        Đang áp dụng
                    </label>
                </div>

                <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeForm">Hủy</button>
                    <button type="submit" :disabled="saving" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ saving ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
