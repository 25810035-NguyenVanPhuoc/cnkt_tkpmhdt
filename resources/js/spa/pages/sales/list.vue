<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../api-client';
import { useAuthStore } from '../../stores/auth-store';
import { formatVnd, formatDateTime } from '../../composables/format';
import { buildQuery } from '../../composables/query-string';

const auth = useAuthStore();

const warehouses = ref([]);
const products = ref([]);

const orders = ref([]);
const ordersMeta = ref(null);
const listFilters = reactive({ status: '', warehouse_id: '' });
const loading = ref(false);
const pageError = ref('');

const STATUS_LABEL = {
    pending: 'Chờ xử lý',
    confirmed: 'Đã xác nhận',
    delivering: 'Đang giao',
    completed: 'Hoàn tất',
    cancelled: 'Đã huỷ',
};
const NEXT_STATUS = {
    pending: { value: 'confirmed', label: 'Xác nhận đơn' },
    confirmed: { value: 'delivering', label: 'Giao hàng' },
    delivering: { value: 'completed', label: 'Hoàn tất' },
};
const CANCELLABLE = ['pending', 'confirmed', 'delivering'];

function isSerialized(productId) {
    return !!products.value.find((p) => p.id === Number(productId))?.is_serialized;
}

function productLabel(id) {
    const p = products.value.find((p) => p.id === id);
    return p ? `${p.sku} — ${p.name}` : '—';
}

async function loadWarehouses() {
    const res = await apiFetch('/warehouses?per_page=100', {}, auth.token);
    warehouses.value = res.data || [];
}

async function loadProducts() {
    const res = await apiFetch('/products?per_page=200&is_active=1', {}, auth.token);
    products.value = res.data || [];
}

async function loadOrders(page = 1) {
    loading.value = true;
    pageError.value = '';
    try {
        const qs = buildQuery({ ...listFilters, page });
        const res = await apiFetch(`/orders${qs}`, {}, auth.token);
        orders.value = res.data || [];
        ordersMeta.value = res.meta || null;
    } catch (e) {
        pageError.value = e.data?.message || 'Không tải được danh sách đơn hàng.';
    } finally {
        loading.value = false;
    }
}

// ---------- tạo đơn hàng ----------
const orderForm = reactive({ warehouse_id: '', customer_phone: '', customer_name: '', shipping_fee: 0, note: '' });
const items = ref([emptyItem()]);
const createError = ref('');
const creating = ref(false);

function emptyItem() {
    return { product_id: '', quantity: 1, discount_amount: 0, unitIds: [], availableUnits: [] };
}

function addItem() {
    items.value.push(emptyItem());
}

function removeItem(index) {
    items.value.splice(index, 1);
    if (!items.value.length) items.value.push(emptyItem());
}

async function refreshItemUnits(item) {
    item.unitIds = [];
    if (!item.product_id || !orderForm.warehouse_id || !isSerialized(item.product_id)) {
        item.availableUnits = [];
        return;
    }
    const qs = buildQuery({ product_id: item.product_id, warehouse_id: orderForm.warehouse_id, status: 'in_stock', per_page: 100 });
    const res = await apiFetch(`/product-units${qs}`, {}, auth.token);
    item.availableUnits = res.data || [];
}

function refreshAllItemUnits() {
    items.value.forEach((item) => refreshItemUnits(item));
}

function lineTotalPreview(item) {
    const product = products.value.find((p) => p.id === Number(item.product_id));
    if (!product) return 0;
    const qty = isSerialized(item.product_id) ? item.unitIds.length : Number(item.quantity || 0);
    return Math.max(0, product.sale_price * qty - Number(item.discount_amount || 0));
}

async function submitOrder() {
    creating.value = true;
    createError.value = '';

    try {
        const payload = {
            warehouse_id: Number(orderForm.warehouse_id),
            customer_phone: orderForm.customer_phone || undefined,
            customer_name: orderForm.customer_name || undefined,
            shipping_fee: Number(orderForm.shipping_fee || 0),
            note: orderForm.note || null,
            items: items.value.map((item) => {
                const base = { product_id: Number(item.product_id), discount_amount: Number(item.discount_amount || 0) };
                if (isSerialized(item.product_id)) {
                    return { ...base, quantity: item.unitIds.length, product_unit_ids: item.unitIds.map(Number) };
                }
                return { ...base, quantity: Number(item.quantity) };
            }),
        };

        await apiFetch('/orders', { method: 'POST', body: JSON.stringify(payload) }, auth.token);

        Object.assign(orderForm, { customer_phone: '', customer_name: '', shipping_fee: 0, note: '' });
        items.value = [emptyItem()];
        await loadOrders();
    } catch (e) {
        createError.value = e.data?.message || 'Không tạo được đơn hàng.';
    } finally {
        creating.value = false;
    }
}

// ---------- chi tiết & xử lý đơn hàng ----------
const expandedOrderId = ref(null);
const orderDetail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const paymentForm = reactive({ amount: '', payment_method: 'cash' });

async function loadDetail(id) {
    detailLoading.value = true;
    detailError.value = '';
    try {
        const res = await apiFetch(`/orders/${id}`, {}, auth.token);
        orderDetail.value = res.data;
    } catch (e) {
        detailError.value = e.data?.message || 'Không tải được chi tiết đơn hàng.';
    } finally {
        detailLoading.value = false;
    }
}

function toggleDetail(order) {
    if (expandedOrderId.value === order.id) {
        expandedOrderId.value = null;
        orderDetail.value = null;
        return;
    }
    expandedOrderId.value = order.id;
    Object.assign(paymentForm, { amount: '', payment_method: 'cash' });
    loadDetail(order.id);
}

async function refreshAfterAction(id) {
    await loadDetail(id);
    await loadOrders(ordersMeta.value?.current_page || 1);
}

async function advanceStatus(order) {
    const next = NEXT_STATUS[order.status];
    if (!next) return;
    detailError.value = '';
    try {
        await apiFetch(`/orders/${order.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: next.value }) }, auth.token);
        await refreshAfterAction(order.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không đổi được trạng thái.';
    }
}

async function cancelOrder(order) {
    if (!confirm(`Huỷ đơn hàng ${order.code}?`)) return;
    detailError.value = '';
    try {
        await apiFetch(`/orders/${order.id}/cancel`, { method: 'POST', body: JSON.stringify({}) }, auth.token);
        await refreshAfterAction(order.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không huỷ được đơn hàng.';
    }
}

async function submitPayment(order) {
    detailError.value = '';
    try {
        await apiFetch(
            `/orders/${order.id}/payments`,
            { method: 'POST', body: JSON.stringify({ amount: Number(paymentForm.amount), payment_method: paymentForm.payment_method }) },
            auth.token,
        );
        Object.assign(paymentForm, { amount: '', payment_method: 'cash' });
        await refreshAfterAction(order.id);
    } catch (e) {
        detailError.value = e.data?.message || 'Không ghi được thanh toán.';
    }
}

onMounted(async () => {
    await Promise.all([loadWarehouses(), loadProducts()]);
    loadOrders();
});
</script>

<template>
    <div>
        <h1 class="mb-4 text-lg font-semibold text-gray-800">Bán hàng (POS)</h1>

        <!-- Form lập đơn hàng -->
        <form class="mb-6 rounded-lg border border-gray-200 bg-white p-4" @submit.prevent="submitOrder">
            <h2 class="mb-3 text-sm font-semibold text-gray-700">Lập đơn hàng mới</h2>

            <div class="mb-3 grid grid-cols-4 gap-3">
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Kho bán</label>
                    <select
                        v-model="orderForm.warehouse_id"
                        required
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        @change="refreshAllItemUnits"
                    >
                        <option value="" disabled>-- Chọn kho --</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm text-gray-600">SĐT khách (tuỳ chọn)</label>
                    <input v-model="orderForm.customer_phone" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Tên khách</label>
                    <input v-model="orderForm.customer_name" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Phí vận chuyển</label>
                    <input v-model.number="orderForm.shipping_fee" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mb-2 text-sm font-medium text-gray-700">Sản phẩm</div>
            <div class="mb-1 grid grid-cols-12 gap-2 text-xs text-gray-500">
                <div class="col-span-4">Sản phẩm</div>
                <div class="col-span-4">Số lượng / Serial-IMEI</div>
                <div class="col-span-2">Giảm giá (đ)</div>
                <div class="col-span-1">Thành tiền</div>
                <div class="col-span-1"></div>
            </div>
            <div v-for="(item, index) in items" :key="index" class="mb-2 grid grid-cols-12 items-start gap-2">
                <select
                    v-model="item.product_id"
                    required
                    class="col-span-4 rounded border border-gray-300 px-2 py-2 text-sm"
                    @change="refreshItemUnits(item)"
                >
                    <option value="" disabled>-- Chọn sản phẩm --</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }} ({{ formatVnd(p.sale_price) }})</option>
                </select>

                <div class="col-span-4">
                    <div v-if="isSerialized(item.product_id)" class="max-h-20 overflow-y-auto rounded border border-gray-300 p-1">
                        <label v-for="u in item.availableUnits" :key="u.id" class="flex items-center gap-1 text-xs">
                            <input type="checkbox" :value="u.id" v-model="item.unitIds" />
                            {{ u.imei_serial }}
                        </label>
                        <p v-if="item.product_id && !item.availableUnits.length" class="text-xs text-gray-400">
                            Chọn kho &amp; hết hàng khả dụng.
                        </p>
                    </div>
                    <input
                        v-else
                        v-model.number="item.quantity"
                        type="number"
                        min="1"
                        placeholder="SL"
                        class="w-full rounded border border-gray-300 px-2 py-2 text-sm"
                    />
                </div>

                <input
                    v-model.number="item.discount_amount"
                    type="number"
                    min="0"
                    placeholder="Giảm giá (đ)"
                    class="col-span-2 rounded border border-gray-300 px-2 py-2 text-sm"
                />

                <div class="col-span-1 pt-2 text-right text-xs text-gray-500">{{ formatVnd(lineTotalPreview(item)) }}</div>

                <button type="button" class="col-span-1 text-sm text-red-600 hover:text-red-800" @click="removeItem(index)">Xoá</button>
            </div>

            <button type="button" class="mb-3 text-sm text-gray-600 hover:text-gray-900" @click="addItem">+ Thêm dòng sản phẩm</button>

            <div>
                <label class="mb-1 block text-sm text-gray-600">Ghi chú</label>
                <input v-model="orderForm.note" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>

            <p v-if="createError" class="mt-3 text-sm text-red-600">{{ createError }}</p>

            <div class="mt-3 flex justify-end">
                <button type="submit" :disabled="creating" class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ creating ? 'Đang tạo...' : 'Tạo đơn hàng' }}
                </button>
            </div>
        </form>

        <!-- Danh sách đơn hàng -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-700">Danh sách đơn hàng</h2>
            <div class="flex gap-2">
                <select v-model="listFilters.status" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadOrders()">
                    <option value="">Tất cả trạng thái</option>
                    <option v-for="(label, value) in STATUS_LABEL" :key="value" :value="value">{{ label }}</option>
                </select>
                <select v-model="listFilters.warehouse_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadOrders()">
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
                        <th class="px-4 py-2">Khách hàng</th>
                        <th class="px-4 py-2">Kho</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2">Tổng tiền</th>
                        <th class="px-4 py-2">Đã thu</th>
                        <th class="px-4 py-2">Ngày</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && !orders.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">Chưa có đơn hàng nào.</td>
                    </tr>
                    <template v-for="order in orders" :key="order.id">
                        <tr class="cursor-pointer border-b border-gray-100 hover:bg-gray-50" @click="toggleDetail(order)">
                            <td class="px-4 py-2 font-mono text-gray-700">{{ order.code }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ order.customer?.name || 'Khách vãng lai' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ order.warehouse?.name }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ STATUS_LABEL[order.status] }}</span>
                            </td>
                            <td class="px-4 py-2 font-medium text-gray-800">{{ formatVnd(order.grand_total) }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ formatVnd(order.paid_amount) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ formatDateTime(order.order_date) }}</td>
                            <td class="px-4 py-2 text-right text-gray-400">{{ expandedOrderId === order.id ? '▲' : '▼' }}</td>
                        </tr>
                        <tr v-if="expandedOrderId === order.id">
                            <td colspan="8" class="border-b border-gray-100 bg-gray-50 px-4 py-4">
                                <div v-if="detailLoading">Đang tải chi tiết...</div>
                                <div v-else-if="orderDetail">
                                    <table class="mb-3 w-full text-sm">
                                        <thead class="text-left text-gray-500">
                                            <tr>
                                                <th class="py-1">Sản phẩm</th>
                                                <th class="py-1">Serial</th>
                                                <th class="py-1">SL</th>
                                                <th class="py-1">Đơn giá</th>
                                                <th class="py-1">Giảm giá</th>
                                                <th class="py-1">Thành tiền</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="it in orderDetail.items" :key="it.id">
                                                <td class="py-1">{{ it.product?.name }}</td>
                                                <td class="py-1 font-mono text-gray-500">{{ it.product_unit?.imei_serial || '—' }}</td>
                                                <td class="py-1">{{ it.quantity }}</td>
                                                <td class="py-1">{{ formatVnd(it.unit_price) }}</td>
                                                <td class="py-1">{{ formatVnd(it.discount_amount) }}</td>
                                                <td class="py-1 font-medium">{{ formatVnd(it.line_total) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="mb-3 grid grid-cols-3 gap-2 text-sm text-gray-600">
                                        <div>Tạm tính: {{ formatVnd(orderDetail.subtotal) }}</div>
                                        <div>Giảm giá: {{ formatVnd(orderDetail.discount_total) }}</div>
                                        <div>Phí vận chuyển: {{ formatVnd(orderDetail.shipping_fee) }}</div>
                                        <div class="font-semibold text-gray-800">Tổng cộng: {{ formatVnd(orderDetail.grand_total) }}</div>
                                        <div>Đã thu: {{ formatVnd(orderDetail.paid_amount) }}</div>
                                        <div>Còn lại: {{ formatVnd(orderDetail.grand_total - orderDetail.paid_amount) }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-xs font-medium text-gray-500">Lịch sử trạng thái</div>
                                        <ul class="text-xs text-gray-500">
                                            <li v-for="h in orderDetail.status_histories" :key="h.id">
                                                {{ formatDateTime(h.created_at) }} — {{ h.from_status || 'mới' }} → {{ h.to_status }}
                                                <span v-if="h.note">({{ h.note }})</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <p v-if="detailError" class="mb-2 text-sm text-red-600">{{ detailError }}</p>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="NEXT_STATUS[orderDetail.status]"
                                            class="rounded bg-gray-800 px-3 py-1.5 text-sm text-white"
                                            @click.stop="advanceStatus(orderDetail)"
                                        >
                                            {{ NEXT_STATUS[orderDetail.status].label }}
                                        </button>
                                        <button
                                            v-if="CANCELLABLE.includes(orderDetail.status)"
                                            class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                                            @click.stop="cancelOrder(orderDetail)"
                                        >
                                            Huỷ đơn
                                        </button>

                                        <div
                                            v-if="orderDetail.paid_amount < orderDetail.grand_total"
                                            class="flex items-center gap-2"
                                            @click.stop
                                        >
                                            <input
                                                v-model.number="paymentForm.amount"
                                                type="number"
                                                min="1"
                                                placeholder="Số tiền thu"
                                                class="w-32 rounded border border-gray-300 px-2 py-1.5 text-sm"
                                            />
                                            <select v-model="paymentForm.payment_method" class="rounded border border-gray-300 px-2 py-1.5 text-sm">
                                                <option value="cash">Tiền mặt</option>
                                                <option value="transfer">Chuyển khoản</option>
                                                <option value="card">Quẹt thẻ</option>
                                            </select>
                                            <button
                                                class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100"
                                                @click="submitPayment(orderDetail)"
                                            >
                                                Ghi nhận thanh toán
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="ordersMeta && ordersMeta.last_page > 1" class="mt-3 flex items-center justify-end gap-2 text-sm">
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="ordersMeta.current_page <= 1"
                @click="loadOrders(ordersMeta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ ordersMeta.current_page }}/{{ ordersMeta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="ordersMeta.current_page >= ordersMeta.last_page"
                @click="loadOrders(ordersMeta.current_page + 1)"
            >
                Sau →
            </button>
        </div>
    </div>
</template>
