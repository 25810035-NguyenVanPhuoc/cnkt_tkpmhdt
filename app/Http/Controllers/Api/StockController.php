<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustRequest;
use App\Http\Requests\StockInRequest;
use App\Http\Requests\StockTransferRequest;
use App\Http\Resources\ProductStockResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InventoryService $inventory) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouse.view', only: ['index', 'movements']),
            new Middleware('permission:warehouse.create', only: ['stockIn']),
            new Middleware('permission:warehouse.update', only: ['adjust', 'transfer']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $stocks = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->paginate($request->integer('per_page', 15));

        return response()->json(ProductStockResource::collection($stocks)->response()->getData(true));
    }

    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovement::query()
            ->with(['product', 'warehouse'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json(StockMovementResource::collection($movements)->response()->getData(true));
    }

    public function stockIn(StockInRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        $warehouse = Warehouse::findOrFail($request->input('warehouse_id'));

        DB::transaction(fn () => $this->inventory->stockIn(
            $product,
            $warehouse,
            $request->input('quantity'),
            $request->input('imei_serials', []),
            [
                'reference_type' => 'manual',
                'reference_id' => null,
                'note' => $request->input('note'),
                'created_by' => $request->user()->id,
            ],
        ));

        return response()->json(['message' => 'Đã nhập kho.']);
    }

    public function adjust(StockAdjustRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        $warehouse = Warehouse::findOrFail($request->input('warehouse_id'));

        $result = DB::transaction(fn () => $this->inventory->adjust(
            $product,
            $warehouse,
            $request->only(['quantity_after', 'product_unit_id', 'status']),
            [
                'reference_type' => 'manual',
                'reference_id' => null,
                'note' => $request->input('note'),
                'created_by' => $request->user()->id,
            ],
        ));

        return response()->json(['message' => 'Đã điều chỉnh tồn kho.', 'data' => $result]);
    }

    public function transfer(StockTransferRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        $from = Warehouse::findOrFail($request->input('from_warehouse_id'));
        $to = Warehouse::findOrFail($request->input('to_warehouse_id'));

        DB::transaction(fn () => $this->inventory->transfer(
            $product,
            $from,
            $to,
            $request->input('quantity'),
            $request->input('product_unit_ids', []),
            [
                'reference_type' => 'transfer',
                'reference_id' => null,
                'note' => $request->input('note'),
                'created_by' => $request->user()->id,
            ],
        ));

        return response()->json(['message' => 'Đã chuyển kho.']);
    }
}
