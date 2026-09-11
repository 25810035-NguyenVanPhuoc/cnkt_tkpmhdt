<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductUnitResource;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductUnitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouse.view'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $units = ProductUnit::query()
            ->with(['product', 'warehouse'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('imei_serial'), fn ($q) => $q->where('imei_serial', 'like', '%'.$request->string('imei_serial').'%'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(ProductUnitResource::collection($units)->response()->getData(true));
    }
}
