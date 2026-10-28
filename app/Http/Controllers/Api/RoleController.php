<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index', 'show', 'catalog']),
            new Middleware('permission:roles.create', only: ['store']),
            new Middleware('permission:roles.update', only: ['update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return response()->json(['data' => RoleResource::collection($roles)]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create(['name' => $request->input('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->input('permissions'));

        return response()->json(['data' => new RoleResource($role->load('permissions'))], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => new RoleResource($role->load('permissions'))]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->name === 'admin' && $request->filled('name') && $request->input('name') !== 'admin') {
            throw ValidationException::withMessages([
                'name' => 'Không thể đổi tên vai trò quản trị (admin).',
            ]);
        }

        if ($request->filled('name')) {
            $role->update(['name' => $request->input('name')]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->input('permissions'));
        }

        return response()->json(['data' => new RoleResource($role->load('permissions'))]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'admin') {
            return response()->json(['message' => 'Không thể xoá vai trò quản trị (admin).'], 422);
        }

        if ($this->employeeCountForRole($role) > 0) {
            return response()->json(['message' => 'Không thể xoá vai trò đang có nhân viên sử dụng.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Đã xoá vai trò.']);
    }

    public function catalog(): JsonResponse
    {
        $groups = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'permissions' => $permissions->pluck('name')->values(),
            ])
            ->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * Đếm số nhân viên đang gán role này bằng truy vấn thẳng bảng model_has_roles,
     * không dùng $role->users() — quan hệ polymorphic của Spatie tự suy model theo
     * guard_name trên một "instance rỗng", việc này không ổn định khi chạy chung
     * nhiều test case trong cùng 1 process PHPUnit.
     */
    private function employeeCountForRole(Role $role): int
    {
        return DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->count();
    }
}
