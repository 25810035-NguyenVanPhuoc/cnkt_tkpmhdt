<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrders) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchasing.view', only: ['index', 'show']),
            new Middleware('permission:purchasing.create', only: ['store']),
            new Middleware('permission:purchasing.update', only: ['markOrdered', 'receive', 'cancel']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'warehouse'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->latest('order_date')
            ->paginate($request->integer('per_page', 15));

        return response()->json(PurchaseOrderResource::collection($purchaseOrders)->response()->getData(true));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrders->createDraft($request->validated(), $request->user());

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource(
            $purchaseOrder->load(['items.product', 'supplier', 'warehouse'])
        )]);
    }

    public function markOrdered(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrders->markOrdered($purchaseOrder);

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrders->receive($purchaseOrder, $request->validated('items'), $request->user());

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrders->cancel($purchaseOrder);

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }
}
