<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouse.view', only: ['index', 'show']),
            new Middleware('permission:warehouse.create', only: ['store']),
            new Middleware('permission:warehouse.update', only: ['update']),
            new Middleware('permission:warehouse.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::query()->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json(WarehouseResource::collection($warehouses)->response()->getData(true));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }

            return Warehouse::create($request->validated())->refresh();
        });

        return response()->json(['data' => new WarehouseResource($warehouse)], 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json(['data' => new WarehouseResource($warehouse)]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        DB::transaction(function () use ($request, $warehouse) {
            if ($request->boolean('is_default')) {
                Warehouse::where('is_default', true)->where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }

            $warehouse->update($request->validated());
        });

        return response()->json(['data' => new WarehouseResource($warehouse->refresh())]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->productStocks()->exists() || $warehouse->productUnits()->exists() || $warehouse->orders()->exists()) {
            return response()->json([
                'message' => 'Không thể xoá kho đang có tồn kho hoặc đơn hàng liên quan.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json(['message' => 'Đã xoá kho.']);
    }
}
