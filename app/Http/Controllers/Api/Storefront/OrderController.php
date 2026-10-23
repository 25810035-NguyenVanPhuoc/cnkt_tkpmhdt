<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\ProductUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorefrontPreviewPromotionRequest;
use App\Http\Requests\StorefrontStoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Đơn hàng khách vãng lai: không cần đăng nhập, khách không tự chọn
     * IMEI/serial nên với sản phẩm is_serialized ta tự chọn sẵn N đơn vị
     * còn tồn kho (in_stock) tại kho mặc định trước khi giao cho
     * OrderService xử lý (giữ đúng logic trừ/khoá tồn kho hiện có).
     */
    public function store(StorefrontStoreOrderRequest $request): JsonResponse
    {
        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::firstOrFail();
        $actor = User::firstOrFail();

        $items = collect($request->validated('items'))->map(function (array $item) use ($warehouse) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->is_serialized) {
                $unitIds = ProductUnit::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('status', ProductUnitStatus::InStock)
                    ->limit($item['quantity'])
                    ->pluck('id');

                if ($unitIds->count() < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->name} không đủ tồn kho.",
                    ]);
                }

                $item['product_unit_ids'] = $unitIds->all();
            }

            return $item;
        })->all();

        // Neu khach DA dang nhap (route nay duoc goi tu web.php - co session),
        // dung thang customer_id cua tai khoan dang nhap de gan don hang -
        // KHONG phu thuoc vao viec SDT nhap tren form co khop chinh xac hay
        // khong (vd form cu bi cache, hoac NV chinh sua o input truoc khi
        // gui). Khach vang lai (chua dang nhap) van resolve theo SDT nhu cu.
        $authCustomer = Auth::guard('customer')->user();

        $order = $this->orders->createOrder([
            'warehouse_id' => $warehouse->id,
            'customer_id' => $authCustomer?->id,
            'customer_name' => $request->validated('customer_name'),
            'customer_phone' => $request->validated('customer_phone'),
            'note' => $request->validated('note'),
            'promotion_code' => $request->validated('promotion_code'),
            'source' => 'storefront',
            'items' => $items,
        ], $actor);

        $order->update([
            'shipping_name' => $request->validated('customer_name'),
            'shipping_phone' => $request->validated('customer_phone'),
            'shipping_address' => $request->validated('shipping_address'),
        ]);

        return response()->json([
            'data' => new OrderResource($order->fresh(['items.product', 'items.productUnit', 'customer', 'warehouse'])),
        ], 201);
    }

    /**
     * Xem truoc giam gia khi khach go ma khuyen mai o trang thanh toan
     * (nut "Ap dung") - khong tao don, chi tinh thu de hien thi lai tong tien.
     */
    public function previewPromotion(StorefrontPreviewPromotionRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        $result = $this->orders->previewPromotion(
            $request->validated('items'),
            $request->validated('promotion_code'),
            $customer,
        );

        return response()->json(['data' => $result]);
    }
}
