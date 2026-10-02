<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show']),
            new Middleware('permission:customers.create', only: ['store']),
            new Middleware('permission:customers.update', only: ['update']),
            new Middleware('permission:customers.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q');
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated())->refresh();

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['data' => new CustomerResource($customer->loadCount('orders'))]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json(['data' => new CustomerResource($customer->loadCount('orders'))]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Đã xoá khách hàng.']);
    }
}
