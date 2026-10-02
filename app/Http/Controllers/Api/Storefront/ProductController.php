<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where('name', 'like', $term);
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 24));

        return response()->json(ProductResource::collection($products)->response()->getData(true));
    }

    public function show(Product $product): JsonResponse
    {
        abort_if(! $product->is_active, 404);

        return response()->json(['data' => new ProductResource($product->load(['category', 'images']))]);
    }
}
