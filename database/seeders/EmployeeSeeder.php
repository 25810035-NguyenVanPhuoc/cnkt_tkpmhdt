<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    private const EMPLOYEES = [
        ['code' => 'NV001', 'name' => 'Nguyễn Văn An', 'role' => 'sales_staff', 'active' => true],
        ['code' => 'NV002', 'name' => 'Trần Thị Bình', 'role' => 'sales_staff', 'active' => true],
        ['code' => 'NV003', 'name' => 'Lê Văn Cường', 'role' => 'sales_staff', 'active' => true],
        ['code' => 'NV004', 'name' => 'Phạm Thị Dung', 'role' => 'sales_staff', 'active' => true],
        ['code' => 'NV005', 'name' => 'Hoàng Văn Em', 'role' => 'sales_staff', 'active' => false],
        ['code' => 'NV006', 'name' => 'Vũ Thị Giang', 'role' => 'warehouse_staff', 'active' => true],
        ['code' => 'NV007', 'name' => 'Đặng Văn Hùng', 'role' => 'warehouse_staff', 'active' => true],
        ['code' => 'NV008', 'name' => 'Bùi Thị Hoa', 'role' => 'warehouse_staff', 'active' => true],
        ['code' => 'NV009', 'name' => 'Ngô Văn Khải', 'role' => 'warehouse_staff', 'active' => true],
        ['code' => 'NV010', 'name' => 'Đỗ Thị Lan', 'role' => 'warehouse_staff', 'active' => false],
    ];

    public function run(): void
    {
        foreach (self::EMPLOYEES as $index => $data) {
            $sequence = $index + 1;
            $email = sprintf('nv%02d@qlibanhang.local', $sequence);

            $employee = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'phone' => sprintf('09%08d', 10000000 + $sequence),
                    'employee_code' => $data['code'],
                    'is_active' => $data['active'],
                ]
            );

            if (! $employee->hasRole($data['role'])) {
                $employee->syncRoles([$data['role']]);
            }
        }
    }
}
