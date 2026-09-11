<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch } from '../../../api-client';
import { useAuthStore } from '../../../stores/auth-store';
import { formatVnd, slugify } from '../../../composables/format';
import { buildQuery } from '../../../composables/query-string';
import MediaPicker from '../../../components/MediaPicker.vue';

const auth = useAuthStore();

const products = ref([]);
const categories = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');

const filters = reactive({ q: '', category_id: '', is_active: '' });

const showForm = ref(false);
const editingId = ref(null);
const formError = ref('');
const saving = ref(false);

const emptyForm = () => ({
    sku: '',
    name: '',
    slug: '',
    category_id: '',
    cost_price: 0,
    sale_price: 0,
    description: '',
    is_serialized: false,
    is_active: true,
});

const form = reactive(emptyForm());
let slugTouched = false;

// Ảnh đã có trên server (chỉ có khi sửa sản phẩm đã tồn tại).
const currentImages = ref([]);
const imageError = ref('');
const uploadingImage = ref(false);
const featuredPickerOpen = ref(false);
const thumbPickerOpen = ref(false);

// Ảnh đã CHỌN từ thư viện (đã upload lên media library) nhưng CHƯA gắn vào sản phẩm —
// dùng khi tạo sản phẩm mới (chưa có id để gắn ảnh vào). Khi sửa sản phẩm đã tồn tại,
// chọn ảnh sẽ gắn ngay (không cần "staged").
const stagedFeatured = ref(null); // { path, url }
const stagedThumbs = ref([]); // [{ path, url }]

function clearStaged() {
    stagedFeatured.value = null;
    stagedThumbs.value = [];
}

function primaryImage(product) {
    return product.images?.find((i) => i.is_primary) || product.images?.[0] || null;
}

function currentPrimary() {
    return currentImages.value.find((i) => i.is_primary) || null;
}

function currentThumbnails() {
    return currentImages.value.filter((i) => !i.is_primary);
}

async function loadCategories() {
    try {
        const res = await apiFetch('/categories?per_page=100', {}, auth.token);
        categories.value = res.data || [];
    } catch (e) {
        // Người dùng có thể không có quyền categories.view — bỏ qua, chỉ ảnh hưởng dropdown.
        categories.value = [];
    }
}

async function loadProducts(page = 1) {
    loading.value = true;
    listError.value = '';

    try {
        const qs = buildQuery({ ...filters, page });
        const res = await apiFetch(`/products${qs}`, {}, auth.token);
        products.value = res.data || [];
        meta.value = res.meta || null;
    } catch (e) {
        listError.value = e.data?.message || 'Không tải được danh sách sản phẩm.';
    } finally {
        loading.value = false;
    }
}

function categoryName(id) {
    return categories.value.find((c) => c.id === id)?.name || '—';
}

function openCreate() {
    editingId.value = null;
    Object.assign(form, emptyForm());
    slugTouched = false;
    formError.value = '';
    imageError.value = '';
    currentImages.value = [];
    clearStaged();
    showForm.value = true;
}

function openEdit(product) {
    editingId.value = product.id;
    Object.assign(form, {
        sku: product.sku,
        name: product.name,
        slug: product.slug,
        category_id: product.category_id,
        cost_price: product.cost_price,
        sale_price: product.sale_price,
        description: product.description || '',
        is_serialized: product.is_serialized,
        is_active: product.is_active,
    });
    slugTouched = true;
    formError.value = '';
    imageError.value = '';
    currentImages.value = product.images || [];
    clearStaged();
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
    clearStaged();
}

async function refreshCurrentImages() {
    if (!editingId.value) return;
    const res = await apiFetch(`/products/${editingId.value}`, {}, auth.token);
    currentImages.value = res.data.images || [];
}

// ---------- ảnh đại diện ----------
function openFeaturedPicker() {
    featuredPickerOpen.value = true;
}

function onFeaturedPicked(file) {
    featuredPickerOpen.value = false;

    if (editingId.value) {
        attachImage(file.path, true);
    } else {
        stagedFeatured.value = { path: file.path, url: file.thumb_url || file.url };
    }
}

function removeStagedFeatured() {
    stagedFeatured.value = null;
}

// ---------- ảnh thumbnail (nhiều ảnh) ----------
function openThumbPicker() {
    thumbPickerOpen.value = true;
}

function onThumbPicked(file) {
    thumbPickerOpen.value = false;

    if (editingId.value) {
        attachImage(file.path, false);
    } else {
        stagedThumbs.value.push({ path: file.path, url: file.thumb_url || file.url });
    }
}

function removeStagedThumb(index) {
    stagedThumbs.value.splice(index, 1);
}

async function attachImage(path, isPrimary, sortOrder = 0) {
    uploadingImage.value = true;
    imageError.value = '';

    try {
        await apiFetch(
            `/products/${editingId.value}/images`,
            { method: 'POST', body: JSON.stringify({ path, is_primary: isPrimary, sort_order: sortOrder }) },
            auth.token,
        );
        await refreshCurrentImages();
        await loadProducts(meta.value?.current_page || 1);
    } catch (e) {
        imageError.value = e.data?.message || 'Không gắn được ảnh vào sản phẩm.';
    } finally {
        uploadingImage.value = false;
    }
}

async function setPrimaryImage(image) {
    imageError.value = '';
    try {
        await apiFetch(
            `/products/${editingId.value}/images/${image.id}`,
            { method: 'PATCH', body: JSON.stringify({ is_primary: true }) },
            auth.token,
        );
        await refreshCurrentImages();
        await loadProducts(meta.value?.current_page || 1);
    } catch (e) {
        imageError.value = e.data?.message || 'Không cập nhật được ảnh đại diện.';
    }
}

async function deleteImage(image) {
    if (!confirm('Xoá ảnh này?')) return;

    imageError.value = '';
    try {
        await apiFetch(`/products/${editingId.value}/images/${image.id}`, { method: 'DELETE' }, auth.token);
        await refreshCurrentImages();
        await loadProducts(meta.value?.current_page || 1);
    } catch (e) {
        imageError.value = e.data?.message || 'Không xoá được ảnh.';
    }
}

// ---------- gắn ảnh đã "staged" (đã có trong thư viện) ngay sau khi tạo sản phẩm mới ----------
async function uploadStagedImages(productId) {
    if (stagedFeatured.value) {
        await apiFetch(
            `/products/${productId}/images`,
            { method: 'POST', body: JSON.stringify({ path: stagedFeatured.value.path, is_primary: true }) },
            auth.token,
        );
    }

    for (let i = 0; i < stagedThumbs.value.length; i++) {
        await apiFetch(
            `/products/${productId}/images`,
            { method: 'POST', body: JSON.stringify({ path: stagedThumbs.value[i].path, sort_order: i }) },
            auth.token,
        );
    }
}

async function submitForm() {
    saving.value = true;
    formError.value = '';

    const payload = {
        sku: form.sku,
        name: form.name,
        slug: form.slug,
        category_id: Number(form.category_id),
        cost_price: Number(form.cost_price),
        sale_price: Number(form.sale_price),
        description: form.description || null,
        is_serialized: form.is_serialized,
        is_active: form.is_active,
    };

    try {
        if (editingId.value) {
            await apiFetch(
                `/products/${editingId.value}`,
                { method: 'PUT', body: JSON.stringify(payload) },
                auth.token,
            );
        } else {
            const created = await apiFetch('/products', { method: 'POST', body: JSON.stringify(payload) }, auth.token);

            if (stagedFeatured.value || stagedThumbs.value.length) {
                try {
                    await uploadStagedImages(created.data.id);
                } catch (e) {
                    formError.value = 'Đã tạo sản phẩm, nhưng một số ảnh chưa tải lên được. Hãy mở lại để thêm ảnh.';
                }
            }
        }

        clearStaged();
        showForm.value = false;
        await loadProducts(meta.value?.current_page || 1);
    } catch (e) {
        formError.value = e.data?.message || 'Không lưu được sản phẩm.';
    } finally {
        saving.value = false;
    }
}

async function deactivate(product) {
    if (!confirm(`Ngừng kinh doanh sản phẩm "${product.name}"?`)) return;

    try {
        await apiFetch(`/products/${product.id}`, { method: 'DELETE' }, auth.token);
        await loadProducts(meta.value?.current_page || 1);
    } catch (e) {
        listError.value = e.data?.message || 'Không thực hiện được.';
    }
}

onMounted(() => {
    loadCategories();
    loadProducts();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">Sản phẩm</h1>
            <button
                class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                @click="openCreate"
            >
                + Thêm sản phẩm
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="filters.q"
                type="text"
                placeholder="Tìm theo tên/SKU..."
                class="rounded border border-gray-300 px-3 py-2 text-sm"
                @keyup.enter="loadProducts()"
            />
            <select v-model="filters.category_id" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadProducts()">
                <option value="">Tất cả danh mục</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.is_active" class="rounded border border-gray-300 px-3 py-2 text-sm" @change="loadProducts()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Đang kinh doanh</option>
                <option value="0">Đã ngừng</option>
            </select>
            <button class="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="loadProducts()">
                Lọc
            </button>
        </div>

        <p v-if="listError" class="mb-3 text-sm text-red-600">{{ listError }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Ảnh</th>
                        <th class="px-4 py-2">SKU</th>
                        <th class="px-4 py-2">Tên sản phẩm</th>
                        <th class="px-4 py-2">Danh mục</th>
                        <th class="px-4 py-2">Giá vốn</th>
                        <th class="px-4 py-2">Giá bán</th>
                        <th class="px-4 py-2">Loại kho</th>
                        <th class="px-4 py-2">Trạng thái</th>
                        <th class="px-4 py-2 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="px-4 py-6 text-center text-gray-400">Đang tải...</td>
                    </tr>
                    <tr v-else-if="!products.length">
                        <td colspan="9" class="px-4 py-6 text-center text-gray-400">Chưa có sản phẩm nào.</td>
                    </tr>
                    <tr v-for="p in products" :key="p.id" class="border-b border-gray-100">
                        <td class="px-4 py-2">
                            <img
                                v-if="primaryImage(p)"
                                :src="primaryImage(p).url"
                                class="h-10 w-10 rounded border border-gray-200 object-cover"
                            />
                            <div v-else class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 text-xs text-gray-300">
                                —
                            </div>
                        </td>
                        <td class="px-4 py-2 font-mono text-gray-600">{{ p.sku }}</td>
                        <td class="px-4 py-2 text-gray-800">{{ p.name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ p.category?.name || categoryName(p.category_id) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ formatVnd(p.cost_price) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ formatVnd(p.sale_price) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ p.is_serialized ? 'Serial/IMEI' : 'Số lượng' }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded px-2 py-0.5 text-xs"
                                :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ p.is_active ? 'Đang kinh doanh' : 'Đã ngừng' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                                    title="Sửa"
                                    @click="openEdit(p)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="p.is_active"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700"
                                    title="Ngừng kinh doanh"
                                    @click="deactivate(p)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 105.636 5.636a9 9 0 0012.728 12.728zM5.636 5.636l12.728 12.728" />
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
                @click="loadProducts(meta.current_page - 1)"
            >
                ← Trước
            </button>
            <span class="text-gray-500">Trang {{ meta.current_page }}/{{ meta.last_page }}</span>
            <button
                class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                @click="loadProducts(meta.current_page + 1)"
            >
                Sau →
            </button>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-20 flex items-center justify-center bg-black/30 p-4">
            <form
                class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
                @submit.prevent="submitForm"
            >
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ editingId ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}
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

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Thông tin sản phẩm -->
                    <div class="grid grid-cols-2 gap-3 md:col-span-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">SKU</label>
                            <input v-model="form.sku" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Danh mục</label>
                            <select v-model="form.category_id" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="" disabled>-- Chọn danh mục --</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Tên sản phẩm</label>
                            <input
                                v-model="form.name"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                @input="onNameInput"
                            />
                        </div>
                        <div class="col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Slug</label>
                            <input
                                v-model="form.slug"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                @input="onSlugInput"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Giá vốn (đ)</label>
                            <input v-model.number="form.cost_price" type="number" min="0" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Giá bán (đ)</label>
                            <input v-model.number="form.sale_price" type="number" min="0" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Mô tả</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                        </div>
                        <label class="col-span-1 flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_serialized" type="checkbox" />
                            Quản lý theo serial/IMEI
                        </label>
                        <label class="col-span-1 flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_active" type="checkbox" />
                            Đang kinh doanh
                        </label>
                    </div>

                    <!-- Ảnh sản phẩm -->
                    <div class="space-y-5 md:col-span-1">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Ảnh đại diện</label>

                            <button
                                type="button"
                                class="relative flex h-36 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 hover:border-violet-400 hover:bg-violet-50/40"
                                @click="openFeaturedPicker"
                            >
                                <img
                                    v-if="currentPrimary()"
                                    :src="currentPrimary().url"
                                    class="absolute inset-0 h-full w-full object-cover"
                                />
                                <img
                                    v-else-if="stagedFeatured"
                                    :src="stagedFeatured.url"
                                    class="absolute inset-0 h-full w-full object-cover"
                                />
                                <span v-else class="flex flex-col items-center gap-1 text-gray-400">
                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs">Chọn ảnh đại diện</span>
                                </span>
                            </button>

                            <button
                                v-if="stagedFeatured && !editingId"
                                type="button"
                                class="mt-1 text-xs text-red-600 hover:text-red-800"
                                @click="removeStagedFeatured"
                            >
                                Bỏ chọn
                            </button>
                            <p v-if="stagedFeatured && !editingId" class="mt-1 text-xs text-gray-400">
                                Sẽ tải lên ngay sau khi lưu sản phẩm.
                            </p>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Ảnh thumbnail (nhiều ảnh)</label>

                            <div class="mb-2 grid grid-cols-3 gap-2">
                                <div v-for="img in currentThumbnails()" :key="img.id" class="group relative">
                                    <img :src="img.url" class="aspect-square w-full rounded border border-gray-200 object-cover" />
                                    <div class="absolute inset-0 flex items-center justify-center gap-1 rounded bg-black/50 opacity-0 group-hover:opacity-100">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-gray-700 hover:bg-white"
                                            title="Đặt làm ảnh đại diện"
                                            @click="setPrimaryImage(img)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-red-600 hover:bg-white"
                                            title="Xoá"
                                            @click="deleteImage(img)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-for="(t, index) in stagedThumbs" :key="index" class="group relative">
                                    <img :src="t.url" class="aspect-square w-full rounded border border-violet-300 object-cover" />
                                    <button
                                        type="button"
                                        class="absolute inset-0 flex items-center justify-center rounded bg-black/50 text-white opacity-0 group-hover:opacity-100"
                                        title="Bỏ chọn"
                                        @click="removeStagedThumb(index)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded border-2 border-dashed border-gray-300 text-gray-400 hover:border-violet-400 hover:bg-violet-50/40"
                                    @click="openThumbPicker"
                                >
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-[11px]">Thêm ảnh</span>
                                </button>
                            </div>

                            <p v-if="stagedThumbs.length && !editingId" class="text-xs text-gray-400">
                                Sẽ tải lên ngay sau khi lưu sản phẩm.
                            </p>
                            <p v-if="uploadingImage" class="text-xs text-gray-400">Đang tải ảnh lên...</p>
                            <p v-if="imageError" class="text-sm text-red-600">{{ imageError }}</p>
                        </div>
                    </div>
                </div>

                <p v-if="formError" class="mt-4 text-sm text-red-600">{{ formError }}</p>

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
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

        <MediaPicker v-if="featuredPickerOpen" @close="featuredPickerOpen = false" @select="onFeaturedPicked" />
        <MediaPicker v-if="thumbPickerOpen" @close="thumbPickerOpen = false" @select="onThumbPicked" />
    </div>
</template>
