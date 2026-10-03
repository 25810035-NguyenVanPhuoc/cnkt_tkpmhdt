<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\ProductUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorefrontStoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
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

        $order = $this->orders->createOrder([
            'warehouse_id' => $warehouse->id,
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
}
