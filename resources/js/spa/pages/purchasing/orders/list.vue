<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';
import { formatVnd, formatDateTime } from '../../../composables/format';
import { buildQuery } from '../../../composables/query-string';

const auth = useAuthStore();

const STATUS_LABEL = {
    draft: 'Nháp',
    ordered: 'Đã đặt NCC',
    partially_received: 'Nhận một phần',
    received: 'Đã nhận đủ',
    cancelled: 'Đã huỷ',
};
const CANCELLABLE = ['draft', 'ordered', 'partially_received'];
const RECEIVABLE = ['ordered', 'partially_received'];

// ---------- nhà cung cấp ----------
const suppliers = ref([]);
const showSupplierForm = ref(false);
const supplierForm = reactive({ name: '', phone: '', contact_name: '' });
const supplierSaving = ref(false);
const supplierError = ref('');

async function loadSuppliers() {
    const res = await apiFetch('/suppliers?per_page=100', {}, auth.token);
    suppliers.value = res.data || [];
}

async function createSupplier() {
    supplierSaving.value = true;
    supplierError.value = '';
    try {
        await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify(supplierForm) }, auth.token);
        Object.assign(supplierForm, { name: '', phone: '', contact_name: '' });
        showSupplierForm.value = false;
        await loadSuppliers();
    } catch (e) {
        supplierError.value = e.data?.message || 'Không tạo được nhà cung cấp.';
    } finally {
        supplierSaving.value = false;
    }
}

async function deleteSupplier(supplier) {
    if (!confirm(`Xoá nhà cung cấp "${supplier.name}"?`)) return;
    try {
        await apiFetch(`/suppliers/${supplier.id}`, { method: 'DELETE' }, auth.token);
        await loadSuppliers();
    } catch (e) {
        alert(e.data?.message || 'Không xoá được nhà cung cấp.');
    }
}

// ---------- sản phẩm & kho (dùng cho form tạo đơn) ----------
const warehouses = ref([]);
const products = ref([]);

async function loadWarehouses() {
    try {
        const res = await apiFetch('/warehouses?per_page=100', {}, auth.token);
        warehouses.value = res.data || [];
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được danh sách kho.';
    }
}

async function loadProducts() {
    try {
        const res = await apiFetch('/products?per_page=200&is_active=1', {}, auth.token);
        products.value = res.data || [];
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được danh sách sản phẩm.';
    }
}

function isSerialized(productId) {
    return !!products.value.find((p) => p.id === Number(productId))?.is_serialized;
}

// ---------- danh sách đơn nhập ----------
const purchaseOrders = ref([]);
const poMeta = ref(null);
const listFilters = reactive({ status: '', warehouse_id: '' });
const loading = ref(false);
const pageError = ref('');

async function loadPurchaseOrders(page = 1) {
    loading.value = true;
    pageError.value = '';
    try {
        const qs = buildQuery({ ...listFilters, page });
        const res = await apiFetch(`/purchase-orders${qs}`, {}, auth.token);
        purchaseOrders.value = res.data || [];
        poMeta.value = res.meta || null;
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được danh sách đơn nhập hàng.';
    } finally {
        loading.value = false;
    }
}

// ---------- tạo đơn nhập ----------
const poForm = reactive({ supplier_id: '', warehouse_id: '' });
const poItems = ref([emptyItem()]);
const createError = ref('');
const creating = ref(false);

function emptyItem() {
    return { product_id: '', quantity_ordered: 1, unit_cost: 0 };
}

function addItem() {
    poItems.value.push(emptyItem());
}

function removeItem(index) {
    poItems.value.splice(index, 1);
    if (!poItems.value.length) poItems.value.push(emptyItem());
}

async function submitPurchaseOrder() {
    creating.value = true;
    createError.value = '';
    try {
        const payload = {
            supplier_id: Number(poForm.supplier_id),
            warehouse_id: Number(poForm.warehouse_id),
            items: poItems.value.map((item) => ({
                product_id: Number(item.product_id),
                quantity_ordered: Number(item.quantity_ordered),
                unit_cost: Number(item.unit_cost),
            })),
        };

        await apiFetch('/purchase-orders', { method: 'POST', body: JSON.stringify(payload) }, auth.token);

        Object.assign(poForm, { supplier_id: '', warehouse_id: '' });
        poItems.value = [emptyItem()];
        await loadPurchaseOrders();
    } catch (e) {
        createError.value = e.data?.message || 'Không tạo được đơn nhập hàng.';
    } finally {
        creating.value = false;
    }
}

// ---------- chi tiết & xử lý đơn nhập ----------
const expandedId = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const receiveForm = ref({});

async function loadDetail(id) {
    detailLoading.value = true;
    detailError.value = '';
    try {
        const res = await apiFetch(`/purchase-orders/${id}`, {}, auth.token);
        detail.value = res.data;
        receiveForm.value = {};
        for (const item of detail.value.items) {
            receiveForm.value[item.id] = { quantity: item.quantity_ordered - item.quantity_received, imei_serials: '' };
        }
    } catch (e) {
        detailError.value = e.data?.message || 'Không tải được chi tiết đơn nhập hàng.';
    } finally {
        detailLoading.value = false;
    }
}

function toggleDetail(po) {
    if (expandedId.value === po.id) {
        expandedId.value = null;
        detail.value = null;
        return;
    }
    expandedId.value = po.id;
    loadDetail(po.id);
}

async function refreshAfterAction(id) {
    await loadDetail(id);
    await loadPurchaseOrders(poMeta.value?.current_page || 1);
}

async function markOrdered(po) {
    detailError.value = '';
    try {
        await apiFetch(`/purchase-orders/${po.id}/order`, { method: 'POST' }, auth.token);
        await refreshAfterAction(po.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không gửi được đơn cho nhà cung cấp.';
    }
}

async function cancelPurchaseOrder(po) {
    if (!confirm(`Huỷ đơn nhập ${po.code}?`)) return;
    detailError.value = '';
    try {
        await apiFetch(`/purchase-orders/${po.id}/cancel`, { method: 'POST' }, auth.token);
        await refreshAfterAction(po.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không huỷ được đơn nhập.';
    }
}

async function submitReceive(po) {
    detailError.value = '';
    try {
        const items = Object.entries(receiveForm.value)
            .map(([purchase_order_item_id, v]) => ({
                purchase_order_item_id: Number(purchase_order_item_id),
                quantity: Number(v.quantity || 0),
                imei_serials: v.imei_serials
                    ? v.imei_serials.split(',').map((s) => s.trim()).filter(Boolean)
                    : [],
            }))
            .filter((i) => i.quantity > 0);

        if (!items.length) {
            detailError.value = 'Chưa nhập số lượng nhận.';
            return;
        }

        await apiFetch(`/purchase-orders/${po.id}/receive`, { method: 'POST', body: JSON.stringify({ items }) }, auth.token);
        await refreshAfterAction(po.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không ghi nhận được việc nhận hàng.';
    }
}

onMounted(async () => {
    await Promise.allSettled([loadSuppliers(), loadWarehouses(), loadProducts()]);
    loadPurchaseOrders();
});
</script>

<template>
    <div>
        <h1 class="mb-4 text-lg font-semibold text-gray-800">Nhập hàng / NCC</h1>

        <!-- Nhà cung cấp -->
        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Nhà cung cấp</h2>
                <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="showSupplierForm = !showSupplierForm">
                    {{ showSupplierForm ? 'Đóng' : '+ Thêm NCC' }}
                </button>
            </div>

            <form v-if="showSupplierForm" class="mb-3 grid grid-cols-4 gap-2" @submit.prevent="createSupplier">
                <input v-model="supplierForm.name" required placeholder="Tên NCC" class="rounded border border-gray-300 px-2 py-1.5 text-sm" />
                <input v-model="supplierForm.contact_name" placeholder="Người liên hệ" class="rounded border border-gray-300 px-2 py-1.5 text-sm" />
                <input v-model="supplierForm.phone" placeholder="SĐT" class="rounded border border-gray-300 px-2 py-1.5 text-sm" />
                <button type="submit" :disabled="supplierSaving" class="rounded bg-gray-800 px-3 py-1.5 text-sm text-white disabled:opacity-50">
                    {{ supplierSaving ? 'Đang lưu...' : 'Lưu' }}
                </button>
            </form>
            <p v-if="supplierError" class="mb-2 text-sm text-red-600">{{ supplierError }}</p>

            <div class="flex flex-wrap gap-2">
                <span
                    v-for="s in suppliers"
                    :key="s.id"
                    class="flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700"
                >
                    {{ s.name }}<span v-if="s.phone" class="text-gray-400">· {{ s.phone }}</span>
                    <button type="button" class="text-red-500 hover:text-red-700" title="Xoá" @click="deleteSupplier(s)">×</button>
                </span>
                <span v-if="!suppliers.length" class="text-sm text-gray-400">Chưa có nhà cung cấp nào.</span>
            </div>
        </div>

        <!-- Tạo đơn nhập -->
        <form class="mb-6 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="submitPurchaseOrder">
            <h2 class="mb-3 text-sm font-semibold text-gray-700">Lập đơn nhập hàng mới</h2>

            <div class="mb-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Nhà cung cấp</label>
                    <select v-model="poForm.supplier_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="" disabled>-- Chọn NCC --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Kho nhận hàng</label>
                    <select v-model="poForm.warehouse_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
            </div>

            <div class="mb-2 text-sm font-medium text-gray-700">Sản phẩm đặt</div>
            <div class="mb-1 grid grid-cols-12 gap-2 text-xs text-gray-500">
                <div class="col-span-5">Sản phẩm</div>
                <div class="col-span-3">Số lượng đặt</div>
                <div class="col-span-3">Giá nhập / đơn vị</div>
                <div class="col-span-1"></div>
            </div>
            <div v-for="(item, index) in poItems" :key="index" class="mb-2 grid grid-cols-12 items-center gap-2">
                <select v-model="item.product_id" required class="col-span-5 rounded border border-gray-300 px-2 py-2 text-sm">
                    <option value="" disabled>-- Chọn sản phẩm --</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option>
                </select>
                <input v-model.number="item.quantity_ordered" type="number" min="1" class="col-span-3 rounded border border-gray-300 px-2 py-2 text-sm" />
                <input v-model.number="item.unit_cost" type="number" min="0" class="col-span-3 rounded border border-gray-300 px-2 py-2 text-sm" />
                <button type="button" class="col-span-1 text-sm text-red-600 hover:text-red-800" @click="removeItem(index)">Xoá</button>
            </div>

            <button type="button" class="mb-3 text-sm text-gray-600 hover:text-gray-900" @click="addItem">+ Thêm dòng sản phẩm</button>

            <p v-if="createError" class="mt-2 text-sm text-red-600">{{ createError }}</p>

            <div class="mt-3 flex justify-end">
                <button type="submit" :disabled="creating" class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ creating ? 'Đang tạo...' : 'Tạo đơn nhập (nháp)' }}
                </button>
            </div>
        </form>

        <!-- Danh sách đơn nhập -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-700">Danh sách đơn nhập hàng</h2>
            <div class="flex gap-2">
                <select v-model="listFilters.status" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadPurchaseOrders()">
                    <option value="">Tất cả trạng thái</option>
                    <option v-for="(label, value) in STATUS_LABEL" :key="value" :value="value">{{ label }}</option>
                </select>
                <select v-model="listFilters.warehouse_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadPurchaseOrders()">
                    <option value="">Tất cả kho</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
            </div>
        </div>

        <p v-if="pageError" class="mb-3 text-sm text-red-600">{{ pageError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Mã đơn</th>
                        <th class="px-4 py-2">NCC</th>
                        <th class="px-4 py-2">Kho nhận</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2">Tổng tiền</th>
                        <th class="px-4 py-2">Ngày đặt</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && !purchaseOrders.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Chưa có đơn nhập hàng nào.</td>
                    </tr>
                    <template v-for="po in purchaseOrders" :key="po.id">
                        <tr class="cursor-pointer border-b border-gray-100 hover:bg-gray-50" @click="toggleDetail(po)">
                            <td class="px-4 py-2 font-mono text-gray-700">{{ po.code }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ po.supplier?.name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ po.warehouse?.name }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ STATUS_LABEL[po.status] }}</span>
                            </td>
                            <td class="px-4 py-2 font-medium text-gray-800">{{ formatVnd(po.total_amount) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ formatDateTime(po.order_date) }}</td>
                            <td class="px-4 py-2 text-right text-gray-400">{{ expandedId === po.id ? '▲' : '▼' }}</td>
                        </tr>
                        <tr v-if="expandedId === po.id">
                            <td colspan="7" class="border-b border-gray-100 bg-gray-50 px-4 py-4">
                                <div v-if="detailLoading">Đang tải chi tiết...</div>
                                <div v-else-if="detail">
                                    <table class="mb-3 w-full text-sm">
                                        <thead class="text-left text-gray-500">
                                            <tr>
                                                <th class="py-1">Sản phẩm</th>
                                                <th class="py-1">Đặt</th>
                                                <th class="py-1">Đã nhận</th>
                                                <th class="py-1">Giá nhập</th>
                                                <th v-if="RECEIVABLE.includes(detail.status)" class="py-1">Nhận thêm</th>
                                                <th v-if="RECEIVABLE.includes(detail.status)" class="py-1">IMEI/serial (nếu có, cách nhau dấu phẩy)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="it in detail.items" :key="it.id">
                                                <td class="py-1">{{ it.product?.name }}</td>
                                                <td class="py-1">{{ it.quantity_ordered }}</td>
                                                <td class="py-1">{{ it.quantity_received }}</td>
                                                <td class="py-1">{{ formatVnd(it.unit_cost) }}</td>
                                                <td v-if="RECEIVABLE.includes(detail.status)" class="py-1">
                                                    <input
                                                        v-model.number="receiveForm[it.id].quantity"
                                                        type="number"
                                                        min="0"
                                                        :max="it.quantity_ordered - it.quantity_received"
                                                        class="w-20 rounded border border-gray-300 px-2 py-1 text-sm"
                                                        @click.stop
                                                    />
                                                </td>
                                                <td v-if="RECEIVABLE.includes(detail.status)" class="py-1">
                                                    <input
                                                        v-model="receiveForm[it.id].imei_serials"
                                                        type="text"
                                                        placeholder="IMEI1, IMEI2..."
                                                        class="w-48 rounded border border-gray-300 px-2 py-1 text-sm"
                                                        @click.stop
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <p v-if="detailError" class="mb-2 text-sm text-red-600">{{ detailError }}</p>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="detail.status === 'draft'"
                                            class="rounded bg-gray-800 px-3 py-1.5 text-sm text-white"
                                            @click.stop="markOrdered(detail)"
                                        >
                                            Gửi cho NCC
                                        </button>
                                        <button
                                            v-if="RECEIVABLE.includes(detail.status)"
                                            class="rounded bg-gray-800 px-3 py-1.5 text-sm text-white"
                                            @click.stop="submitReceive(detail)"
                                        >
                                            Ghi nhận nhận hàng
                                        </button>
                                        <button
                                            v-if="CANCELLABLE.includes(detail.status)"
                                            class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                                            @click.stop="cancelPurchaseOrder(detail)"
                                        >
                                            Huỷ đơn
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="poMeta && poMeta.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-sm">
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="poMeta.current_page <= 1"
                @click="loadPurchaseOrders(poMeta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ poMeta.current_page }}/{{ poMeta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="poMeta.current_page >= poMeta.last_page"
                @click="loadPurchaseOrders(poMeta.current_page + 1)"
            >
                Sau →
            </button>
        </div>
    </div>
</template>
