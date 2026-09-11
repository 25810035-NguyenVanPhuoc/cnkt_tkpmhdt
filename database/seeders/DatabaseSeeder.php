<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Danh sách module theo 8 use case của đề tài — mỗi module có 4 quyền CRUD cơ bản.
     */
    private const MODULES = [
        'sales', 'products', 'categories', 'warehouse',
        'purchasing', 'customers', 'employees', 'promotions',
        'roles', 'settings',
    ];

    private const ACTIONS = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        $permissionNames = [];

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permissionNames[] = "{$module}.{$action}";
            }
        }

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissionNames);

        $admin = User::firstOrCreate(
            ['email' => 'admin@qlibanhang.local'],
            [
                'name' => 'Quản trị viên',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        Warehouse::firstOrCreate(
            ['name' => 'Kho chính'],
            ['is_default' => true],
        );

        $salesStaffRole = Role::firstOrCreate(['name' => 'sales_staff', 'guard_name' => 'web']);
        $salesStaffRole->syncPermissions([
            'sales.view', 'sales.create', 'sales.update',
            'products.view', 'categories.view',
        ]);

        $warehouseStaffRole = Role::firstOrCreate(['name' => 'warehouse_staff', 'guard_name' => 'web']);
        $warehouseStaffRole->syncPermissions([
            'warehouse.view', 'warehouse.create', 'warehouse.update', 'warehouse.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view',
        ]);

        $this->call(EmployeeSeeder::class);

        // ProductSeeder xử lý ảnh thật (resize + thumbnail cho 127 file) — quá chậm để chạy
        // lại ở mỗi test gọi $this->seed(DatabaseSeeder::class). Các test hiện có tự tạo sản
        // phẩm riêng qua ProductFactory, không phụ thuộc catalog demo này.
        if (! app()->environment('testing')) {
            $this->call(ProductSeeder::class);
        }
    }
}
