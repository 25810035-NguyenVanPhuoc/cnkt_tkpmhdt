<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\PayOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OrderController extends Controller implements HasMiddleware
{
    public function __construct(private readonly OrderService $orders) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.view', only: ['index', 'show', 'invoice']),
            new Middleware('permission:sales.create', only: ['store']),
            new Middleware('permission:sales.update', only: ['updateStatus', 'cancel', 'pay']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['customer', 'warehouse'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->latest('order_date')
            ->paginate($request->integer('per_page', 15));

        return response()->json(OrderResource::collection($orders)->response()->getData(true));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->createOrder($request->validated(), $request->user());

        return response()->json(['data' => new OrderResource($order)], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['data' => new OrderResource(
            $order->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories'])
        )]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->changeStatus(
            $order,
            OrderStatus::from($request->input('status')),
            $request->input('note'),
            $request->user(),
        );

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->cancel($order, $request->input('note'), $request->user());

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->recordPayment($order, $request->integer('amount'), $request->string('payment_method')->toString());

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function invoice(Order $order): JsonResponse
    {
        return response()->json(['data' => new OrderResource(
            $order->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories', 'user'])
        )]);
    }
}
