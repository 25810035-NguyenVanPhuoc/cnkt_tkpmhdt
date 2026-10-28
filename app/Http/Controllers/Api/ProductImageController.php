<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Http\Requests\UpdateProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.update'),
        ];
    }

    /**
     * Gắn một ảnh đã có trong thư viện media (chọn qua MediaPicker) vào sản phẩm.
     * Việc upload thật diễn ra ở MediaController::upload — chỗ này chỉ tạo liên kết.
     */
    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $image = DB::transaction(function () use ($request, $product) {
            if ($request->boolean('is_primary')) {
                $product->images()->update(['is_primary' => false]);
            }

            return $product->images()->create([
                'path' => $request->string('path'),
                'sort_order' => $request->integer('sort_order', 0),
                'is_primary' => $request->boolean('is_primary', false),
            ]);
        });

        return response()->json(['data' => new ProductImageResource($image)], 201);
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $image): JsonResponse
    {
        DB::transaction(function () use ($request, $product, $image) {
            if ($request->boolean('is_primary')) {
                $product->images()->update(['is_primary' => false]);
            }

            $image->update($request->validated());
        });

        return response()->json(['data' => new ProductImageResource($image->refresh())]);
    }

    /** Chỉ gỡ liên kết khỏi sản phẩm — file thật vẫn còn trong thư viện media để dùng lại. */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $image->delete();

        return response()->json(['message' => 'Đã gỡ ảnh khỏi sản phẩm.']);
    }
}
