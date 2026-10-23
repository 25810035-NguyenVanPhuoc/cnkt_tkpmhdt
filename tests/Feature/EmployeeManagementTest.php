<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo(['employees.view', 'employees.create', 'employees.update', 'employees.delete', 'roles.view']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_roles_endpoint_returns_seeded_roles(): void
    {
        $response = $this->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertEqualsCanonicalizing(['admin', 'sales_staff', 'warehouse_staff'], $names->all());
    }

    public function test_admin_can_create_employee_with_role(): void
    {
        $role = Role::where('name', 'sales_staff')->first();

        $response = $this->postJson('/api/employees', [
            'name' => 'Nhan Vien Test',
            'email' => 'nvtest@qlibanhang.local',
            'password' => 'password',
            'phone' => '0900000000',
            'employee_code' => 'NVTEST',
            'role_id' => $role->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role.name', 'sales_staff');

        $employee = User::where('email', 'nvtest@qlibanhang.local')->first();
        $this->assertTrue($employee->hasRole('sales_staff'));
    }

    public function test_creating_employee_generates_temp_password_and_requires_change(): void
    {
        // Admin khong con tu nhap mat khau khi tao nhan vien - he thong tu
        // sinh mat khau tam, tra ve 1 lan trong response, va bat doi mat khau
        // o lan dang nhap dau tien.
        $role = Role::where('name', 'sales_staff')->first();

        $response = $this->postJson('/api/employees', [
            'name' => 'Nhan Vien Moi',
            'email' => 'nhanvienmoi@qlibanhang.local',
            'role_id' => $role->id,
        ]);

        $response->assertCreated();
        $tempPassword = $response->json('temp_password');
        $this->assertNotEmpty($tempPassword);

        $employee = User::where('email', 'nhanvienmoi@qlibanhang.local')->first();
        $this->assertTrue($employee->must_change_password);
        $this->assertTrue(Hash::check($tempPassword, $employee->password));
    }

    public function test_locking_employee_prevents_login(): void
    {
        $employee = User::where('employee_code', 'NV001')->first();

        $response = $this->patchJson("/api/employees/{$employee->id}/status", ['is_active' => false]);
        $response->assertOk();

        $login = $this->postJson('/api/login', [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $login->assertStatus(422);
    }

    public function test_admin_cannot_lock_own_account(): void
    {
        $response = $this->patchJson("/api/employees/{$this->admin->id}/status", ['is_active' => false]);

        $response->assertStatus(422);
    }

    public function test_resetting_employee_password_via_edit_also_forces_change_on_next_login(): void
    {
        $employee = User::where('employee_code', 'NV001')->first();
        $employee->update(['must_change_password' => false]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'password' => 'reset-by-admin-1',
        ]);

        $response->assertOk();

        $employee->refresh();
        $this->assertTrue($employee->must_change_password);
        $this->assertTrue(Hash::check('reset-by-admin-1', $employee->password));
    }

    public function test_updating_employee_role_replaces_previous_role(): void
    {
        $employee = User::where('employee_code', 'NV001')->first();
        $this->assertTrue($employee->hasRole('sales_staff'));

        $warehouseRole = Role::where('name', 'warehouse_staff')->first();

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'role_id' => $warehouseRole->id,
        ]);

        $response->assertOk();

        $employee->refresh();
        $this->assertTrue($employee->hasRole('warehouse_staff'));
        $this->assertFalse($employee->hasRole('sales_staff'));
    }
}
