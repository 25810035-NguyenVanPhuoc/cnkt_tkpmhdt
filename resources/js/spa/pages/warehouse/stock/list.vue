<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';
import { formatDateTime } from '../../../composables/format';
import { buildQuery } from '../../../composables/query-string';

const auth = useAuthStore();

const warehouses = ref([]);
const products = ref([]);

const activeTab = ref('stock');

const stockRows = ref([]);
const stockMeta = ref(null);
const stockFilters = reactive({ warehouse_id: '', product_id: '' });

const unitRows = ref([]);
const unitMeta = ref(null);
const unitFilters = reactive({ warehouse_id: '', product_id: '', status: '', imei_serial: '' });

const moveRows = ref([]);
const moveMeta = ref(null);
const moveFilters = reactive({ warehouse_id: '', product_id: '', type: '' });

const loading = ref(false);
const pageError = ref('');

function warehouseName(id) {
    return warehouses.value.find((w) => w.id === id)?.name || '—';
}

function productLabel(id) {
    const p = products.value.find((p) => p.id === id);
    return p ? `${p.sku} — ${p.name}` : '—';
}

function isSerialized(productId) {
    return !!products.value.find((p) => p.id === Number(productId))?.is_serialized;
}

async function loadWarehouses() {
    const res = await apiFetch('/warehouses?per_page=100', {}, auth.token);
    warehouses.value = res.data || [];
}

async function loadProducts() {
    const res = await apiFetch('/products?per_page=200&is_active=1', {}, auth.token);
    products.value = res.data || [];
}

async function loadStock(page = 1) {
    loading.value = true;
    pageError.value = '';
    try {
        const qs = buildQuery({ ...stockFilters, page });
        const res = await apiFetch(`/stock${qs}`, {}, auth.token);
        stockRows.value = res.data || [];
        stockMeta.value = res.meta || null;
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được tồn kho.';
    } finally {
        loading.value = false;
    }
}

async function loadUnits(page = 1) {
    loading.value = true;
    pageError.value = '';
    try {
        const qs = buildQuery({ ...unitFilters, page });
        const res = await apiFetch(`/product-units${qs}`, {}, auth.token);
        unitRows.value = res.data || [];
        unitMeta.value = res.meta || null;
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được danh sách serial/IMEI.';
    } finally {
        loading.value = false;
    }
}

async function loadMovements(page = 1) {
    loading.value = true;
    pageError.value = '';
    try {
        const qs = buildQuery({ ...moveFilters, page });
        const res = await apiFetch(`/stock/movements${qs}`, {}, auth.token);
        moveRows.value = res.data || [];
        moveMeta.value = res.meta || null;
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được lịch sử biến động.';
    } finally {
        loading.value = false;
    }
}

function reloadActiveTab(page = 1) {
    if (activeTab.value === 'stock') loadStock(page);
    else if (activeTab.value === 'units') loadUnits(page);
    else loadMovements(page);
}

watch(activeTab, () => reloadActiveTab());

// ---------- modals: nhập kho / điều chỉnh / chuyển kho ----------
const activeModal = ref(null);
const modalError = ref('');
const saving = ref(false);

const formIn = reactive({ product_id: '', warehouse_id: '', quantity: '', imei_text: '', note: '' });
const formAdjust = reactive({ product_id: '', warehouse_id: '', quantity_after: '', product_unit_id: '', status: 'in_stock', note: '' });
const formTransfer = reactive({ product_id: '', from_warehouse_id: '', to_warehouse_id: '', quantity: '', selectedUnitIds: [], note: '' });

const adjustUnits = ref([]);
const transferUnits = ref([]);

async function fetchUnits(target, productId, warehouseId, status) {
    if (!productId || !warehouseId) {
        target.value = [];
        return;
    }
    const qs = buildQuery({ product_id: productId, warehouse_id: warehouseId, status, per_page: 100 });
    const res = await apiFetch(`/product-units${qs}`, {}, auth.token);
    target.value = res.data || [];
}

watch([() => formAdjust.product_id, () => formAdjust.warehouse_id], () => {
    formAdjust.product_unit_id = '';
    if (isSerialized(formAdjust.product_id)) {
        fetchUnits(adjustUnits, formAdjust.product_id, formAdjust.warehouse_id, '');
    }
});

watch([() => formTransfer.product_id, () => formTransfer.from_warehouse_id], () => {
    formTransfer.selectedUnitIds = [];
    if (isSerialized(formTransfer.product_id)) {
        fetchUnits(transferUnits, formTransfer.product_id, formTransfer.from_warehouse_id, 'in_stock');
    }
});

function openModal(type) {
    modalError.value = '';
    if (type === 'in') Object.assign(formIn, { product_id: '', warehouse_id: '', quantity: '', imei_text: '', note: '' });
    if (type === 'adjust') Object.assign(formAdjust, { product_id: '', warehouse_id: '', quantity_after: '', product_unit_id: '', status: 'in_stock', note: '' });
    if (type === 'transfer') Object.assign(formTransfer, { product_id: '', from_warehouse_id: '', to_warehouse_id: '', quantity: '', selectedUnitIds: [], note: '' });
    activeModal.value = type;
}

function closeModal() {
    activeModal.value = null;
}

async function submitIn() {
    saving.value = true;
    modalError.value = '';
    try {
        const payload = {
            product_id: Number(formIn.product_id),
            warehouse_id: Number(formIn.warehouse_id),
            note: formIn.note || null,
        };
        if (isSerialized(formIn.product_id)) {
            payload.imei_serials = formIn.imei_text.split(/[\n,]/).map((s) => s.trim()).filter(Boolean);
        } else {
            payload.quantity = Number(formIn.quantity);
        }
        await apiFetch('/stock/in', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        closeModal();
        reloadActiveTab();
    } catch (e) {
        modalError.value = e.data?.message || 'Không nhập được kho.';
    } finally {
        saving.value = false;
    }
}

async function submitAdjust() {
    saving.value = true;
    modalError.value = '';
    try {
        const payload = {
            product_id: Number(formAdjust.product_id),
            warehouse_id: Number(formAdjust.warehouse_id),
            note: formAdjust.note || null,
        };
        if (isSerialized(formAdjust.product_id)) {
            payload.product_unit_id = Number(formAdjust.product_unit_id);
            payload.status = formAdjust.status;
        } else {
            payload.quantity_after = Number(formAdjust.quantity_after);
        }
        await apiFetch('/stock/adjust', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        closeModal();
        reloadActiveTab();
    } catch (e) {
        modalError.value = e.data?.message || 'Không điều chỉnh được tồn kho.';
    } finally {
        saving.value = false;
    }
}

async function submitTransfer() {
    saving.value = true;
    modalError.value = '';
    try {
        const payload = {
            product_id: Number(formTransfer.product_id),
            from_warehouse_id: Number(formTransfer.from_warehouse_id),
            to_warehouse_id: Number(formTransfer.to_warehouse_id),
            note: formTransfer.note || null,
        };
        if (isSerialized(formTransfer.product_id)) {
            payload.product_unit_ids = formTransfer.selectedUnitIds.map(Number);
        } else {
            payload.quantity = Number(formTransfer.quantity);
        }
        await apiFetch('/stock/transfer', { method: 'POST', body: JSON.stringify(payload) }, auth.token);
        closeModal();
        reloadActiveTab();
    } catch (e) {
        modalError.value = e.data?.message || 'Không chuyển được kho.';
    } finally {
        saving.value = false;
    }
}

const formInIsSerialized = computed(() => isSerialized(formIn.product_id));
const formAdjustIsSerialized = computed(() => isSerialized(formAdjust.product_id));
const formTransferIsSerialized = computed(() => isSerialized(formTransfer.product_id));

onMounted(async () => {
    await Promise.all([loadWarehouses(), loadProducts()]);
    loadStock();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Kho hàng</h1>
            <div class="flex gap-2">
                <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="openModal('in')">
                    Nhập kho
                </button>
                <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="openModal('adjust')">
                    Điều chỉnh / kiểm kê
                </button>
                <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="openModal('transfer')">
                    Chuyển kho
                </button>
            </div>
        </div>

        <div class="mb-4 flex gap-1 border-b border-gray-200">
            <button
                class="px-3 py-2 text-sm"
                :class="activeTab === 'stock' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500'"
                @click="activeTab = 'stock'"
            >
                Tồn kho theo số lượng
            </button>
            <button
                class="px-3 py-2 text-sm"
                :class="activeTab === 'units' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500'"
                @click="activeTab = 'units'"
            >
                Serial / IMEI
            </button>
            <button
                class="px-3 py-2 text-sm"
                :class="activeTab === 'movements' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500'"
                @click="activeTab = 'movements'"
            >
                Lịch sử biến động
            </button>
        </div>

        <p v-if="pageError" class="mb-3 text-sm text-red-600">{{ pageError }}</p>

        <!-- Tồn kho theo số lượng -->
        <div v-if="activeTab === 'stock'">
            <div class="mb-3 flex flex-wrap gap-2">
                <select v-model="stockFilters.warehouse_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadStock()">
                    <option value="">Tất cả kho</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <select v-model="stockFilters.product_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadStock()">
                    <option value="">Tất cả sản phẩm</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Sản phẩm</th>
                            <th class="px-4 py-2">Kho</th>
                            <th class="px-4 py-2">Số lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!loading && !stockRows.length">
                            <td colspan="3" class="px-4 py-6 text-center text-gray-400">Chưa có dữ liệu.</td>
                        </tr>
                        <tr v-for="row in stockRows" :key="row.id" class="border-b border-gray-100">
                            <td class="px-4 py-2">{{ row.product?.sku }} — {{ row.product?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ row.warehouse?.name }}</td>
                            <td class="px-4 py-2 font-medium text-gray-800">{{ row.quantity }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Serial / IMEI -->
        <div v-if="activeTab === 'units'">
            <div class="mb-3 flex flex-wrap gap-2">
                <select v-model="unitFilters.warehouse_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadUnits()">
                    <option value="">Tất cả kho</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <select v-model="unitFilters.product_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadUnits()">
                    <option value="">Tất cả sản phẩm</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                </select>
                <select v-model="unitFilters.status" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadUnits()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="in_stock">Trong kho</option>
                    <option value="reserved">Đã giữ chỗ</option>
                    <option value="sold">Đã bán</option>
                    <option value="returned">Đã trả</option>
                    <option value="damaged">Hỏng</option>
                </select>
                <input
                    v-model="unitFilters.imei_serial"
                    placeholder="Tìm IMEI/serial..."
                    class="rounded border border-gray-300 px-3 py-2 text-sm"
                    @keyup.enter="loadUnits()"
                />
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">IMEI / Serial</th>
                            <th class="px-4 py-2">Sản phẩm</th>
                            <th class="px-4 py-2">Kho</th>
                            <th class="px-4 py-2">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!loading && !unitRows.length">
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Chưa có dữ liệu.</td>
                        </tr>
                        <tr v-for="u in unitRows" :key="u.id" class="border-b border-gray-100">
                            <td class="px-4 py-2 font-mono text-gray-600">{{ u.imei_serial }}</td>
                            <td class="px-4 py-2">{{ u.product?.sku }} — {{ u.product?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ u.warehouse?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ u.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lịch sử biến động -->
        <div v-if="activeTab === 'movements'">
            <div class="mb-3 flex flex-wrap gap-2">
                <select v-model="moveFilters.warehouse_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadMovements()">
                    <option value="">Tất cả kho</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <select v-model="moveFilters.product_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadMovements()">
                    <option value="">Tất cả sản phẩm</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                </select>
                <select v-model="moveFilters.type" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadMovements()">
                    <option value="">Tất cả loại</option>
                    <option value="in">Nhập</option>
                    <option value="out">Xuất</option>
                    <option value="adjustment">Điều chỉnh</option>
                    <option value="transfer">Chuyển kho</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Thời gian</th>
                            <th class="px-4 py-2">Sản phẩm</th>
                            <th class="px-4 py-2">Kho</th>
                            <th class="px-4 py-2">Loại</th>
                            <th class="px-4 py-2">Số lượng</th>
                            <th class="px-4 py-2">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!loading && !moveRows.length">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Chưa có dữ liệu.</td>
                        </tr>
                        <tr v-for="m in moveRows" :key="m.id" class="border-b border-gray-100">
                            <td class="px-4 py-2 text-gray-500">{{ formatDateTime(m.created_at) }}</td>
                            <td class="px-4 py-2">{{ m.product?.sku }} — {{ m.product?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ m.warehouse?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ m.type }}</td>
                            <td class="px-4 py-2" :class="m.quantity < 0 ? 'text-red-600' : 'text-green-700'">
                                {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ m.note }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: Nhập kho -->
        <div v-if="activeModal === 'in'" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitIn">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Nhập kho</h2>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Đóng" @click="closeModal">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Sản phẩm</label>
                        <select v-model="formIn.product_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn sản phẩm --</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Kho</label>
                        <select v-model="formIn.warehouse_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn kho --</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div v-if="formInIsSerialized">
                        <label class="mb-1 block text-sm text-gray-600">Danh sách IMEI/serial (mỗi dòng 1 mã)</label>
                        <textarea v-model="formIn.imei_text" rows="4" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono"></textarea>
                    </div>
                    <div v-else>
                        <label class="mb-1 block text-sm text-gray-600">Số lượng</label>
                        <input v-model.number="formIn.quantity" type="number" min="1" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Ghi chú</label>
                        <input v-model="formIn.note" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <p v-if="modalError" class="mt-3 text-sm text-red-600">{{ modalError }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeModal">Hủy</button>
                    <button type="submit" :disabled="saving" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ saving ? 'Đang lưu...' : 'Nhập kho' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal: Điều chỉnh / kiểm kê -->
        <div v-if="activeModal === 'adjust'" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitAdjust">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Điều chỉnh / kiểm kê</h2>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Đóng" @click="closeModal">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Sản phẩm</label>
                        <select v-model="formAdjust.product_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn sản phẩm --</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Kho</label>
                        <select v-model="formAdjust.warehouse_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn kho --</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <template v-if="formAdjustIsSerialized">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Đơn vị (IMEI/serial)</label>
                            <select v-model="formAdjust.product_unit_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="" disabled>-- Chọn đơn vị --</option>
                                <option v-for="u in adjustUnits" :key="u.id" :value="u.id">{{ u.imei_serial }} ({{ u.status }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Trạng thái mới</label>
                            <select v-model="formAdjust.status" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="in_stock">Trong kho</option>
                                <option value="damaged">Hỏng</option>
                            </select>
                        </div>
                    </template>
                    <div v-else>
                        <label class="mb-1 block text-sm text-gray-600">Số lượng thực tế sau kiểm kê</label>
                        <input v-model.number="formAdjust.quantity_after" type="number" min="0" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Ghi chú</label>
                        <input v-model="formAdjust.note" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <p v-if="modalError" class="mt-3 text-sm text-red-600">{{ modalError }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeModal">Hủy</button>
                    <button type="submit" :disabled="saving" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ saving ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal: Chuyển kho -->
        <div v-if="activeModal === 'transfer'" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @submit.prevent="submitTransfer">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">Chuyển kho</h2>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Đóng" @click="closeModal">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Sản phẩm</label>
                        <select v-model="formTransfer.product_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>-- Chọn sản phẩm --</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Kho nguồn</label>
                            <select v-model="formTransfer.from_warehouse_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="" disabled>-- Chọn kho --</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Kho đích</label>
                            <select v-model="formTransfer.to_warehouse_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="" disabled>-- Chọn kho --</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="formTransferIsSerialized">
                        <label class="mb-1 block text-sm text-gray-600">Chọn đơn vị (IMEI/serial) cần chuyển</label>
                        <div class="max-h-32 overflow-y-auto rounded border border-gray-300 p-2">
                            <label v-for="u in transferUnits" :key="u.id" class="flex items-center gap-2 py-0.5 text-sm">
                                <input type="checkbox" :value="u.id" v-model="formTransfer.selectedUnitIds" />
                                {{ u.imei_serial }}
                            </label>
                            <p v-if="!transferUnits.length" class="text-sm text-gray-400">Không có đơn vị khả dụng tại kho nguồn.</p>
                        </div>
                    </div>
                    <div v-else>
                        <label class="mb-1 block text-sm text-gray-600">Số lượng</label>
                        <input v-model.number="formTransfer.quantity" type="number" min="1" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Ghi chú</label>
                        <input v-model="formTransfer.note" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <p v-if="modalError" class="mt-3 text-sm text-red-600">{{ modalError }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="closeModal">Hủy</button>
                    <button type="submit" :disabled="saving" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {{ saving ? 'Đang lưu...' : 'Chuyển kho' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
