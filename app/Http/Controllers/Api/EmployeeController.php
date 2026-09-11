<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeStatusRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:employees.view', only: ['index', 'show']),
            new Middleware('permission:employees.create', only: ['store']),
            new Middleware('permission:employees.update', only: ['update']),
            new Middleware('permission:employees.delete', only: ['updateStatus']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $employees = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_code', 'like', $term));
            })
            ->when($request->filled('role_id'), function ($query) use ($request) {
                $query->whereHas('roles', fn ($q) => $q->where('id', $request->integer('role_id')));
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json(EmployeeResource::collection($employees)->response()->getData(true));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = User::create($request->safe()->except(['role_id']))->refresh();
        $employee->assignRole(Role::findOrFail($request->integer('role_id')));

        return response()->json(['data' => new EmployeeResource($employee->load('roles'))], 201);
    }

    public function show(User $employee): JsonResponse
    {
        return response()->json(['data' => new EmployeeResource($employee->load('roles'))]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        $data = $request->safe()->except(['role_id', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password');
        }

        $employee->update($data);

        if ($request->filled('role_id')) {
            $employee->syncRoles([Role::findOrFail($request->integer('role_id'))]);
        }

        return response()->json(['data' => new EmployeeResource($employee->load('roles'))]);
    }

    public function updateStatus(UpdateEmployeeStatusRequest $request, User $employee): JsonResponse
    {
        if ($employee->id === $request->user()->id && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'Không thể tự khoá tài khoản của chính mình.',
            ]);
        }

        $employee->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['data' => new EmployeeResource($employee->load('roles'))]);
    }
}
