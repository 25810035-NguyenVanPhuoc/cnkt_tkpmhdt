<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:categories.view', only: ['index', 'show']),
            new Middleware('permission:categories.create', only: ['store']),
            new Middleware('permission:categories.update', only: ['update']),
            new Middleware('permission:categories.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json(CategoryResource::collection($categories)->response()->getData(true));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated())->refresh();

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if (Product::where('category_id', $category->id)->exists()) {
            return response()->json([
                'message' => 'Không thể xoá danh mục đang có sản phẩm.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Đã xoá danh mục.']);
    }
}
