<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Customer;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PromotionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:promotions.view', only: ['index', 'show']),
            new Middleware('permission:promotions.create', only: ['store']),
            new Middleware('permission:promotions.update', only: ['update', 'assignCustomer', 'unassignCustomer']),
            new Middleware('permission:promotions.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $promotions = Promotion::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json(PromotionResource::collection($promotions)->response()->getData(true));
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = Promotion::create($request->validated())->refresh();

        return response()->json(['data' => new PromotionResource($promotion)], 201);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json(['data' => new PromotionResource($promotion->load('customers'))]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($request->validated());

        return response()->json(['data' => new PromotionResource($promotion->load('customers'))]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return response()->json(['message' => 'Đã xoá khuyến mãi.']);
    }

    public function assignCustomer(Request $request, Promotion $promotion): JsonResponse
    {
        $customer = Customer::findOrFail($request->integer('customer_id'));

        $promotion->customers()->syncWithoutDetaching([$customer->id => ['assigned_at' => now()]]);

        return response()->json(['data' => new PromotionResource($promotion->load('customers'))]);
    }

    public function unassignCustomer(Promotion $promotion, Customer $customer): JsonResponse
    {
        $promotion->customers()->detach($customer->id);

        return response()->json(['data' => new PromotionResource($promotion->load('customers'))]);
    }
}
