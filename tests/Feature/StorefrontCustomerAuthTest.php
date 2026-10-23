<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorefrontCustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/dang-ky', [
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'email' => 'a@example.com',
            'address' => '123 Test Street',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('storefront.home'));
        $this->assertDatabaseHas('customers', ['phone' => '0901234567', 'name' => 'Nguyen Van A']);
        $this->assertAuthenticated('customer');
    }

    public function test_registration_fails_with_duplicate_phone(): void
    {
        Customer::create(['name' => 'Existing', 'phone' => '0901234567', 'password' => Hash::make('x')]);

        $response = $this->post('/dang-ky', [
            'name' => 'Someone Else',
            'phone' => '0901234567',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_customer_can_login_with_correct_credentials(): void
    {
        Customer::create([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post('/dang-nhap', [
            'phone' => '0901234567',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('storefront.home'));
        $this->assertAuthenticated('customer');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Customer::create([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post('/dang-nhap', [
            'phone' => '0901234567',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_guest_only_customers_without_password_cannot_login(): void
    {
        // Khach vang lai tao qua checkout/CRM, chua tung dang ky (password NULL).
        Customer::create(['name' => 'Khach Vang Lai', 'phone' => '0909999999', 'password' => null]);

        $response = $this->post('/dang-nhap', [
            'phone' => '0909999999',
            'password' => 'anything',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_my_orders_page_requires_login(): void
    {
        $this->get('/tai-khoan/don-hang')->assertRedirect();
    }

    public function test_logged_in_customer_can_view_my_orders_page(): void
    {
        $customer = Customer::create([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($customer, 'customer')->get('/tai-khoan/don-hang');

        $response->assertOk();
    }

    public function test_order_placed_while_logged_in_shows_up_in_my_orders(): void
    {
        // Regression: don hang qua /thanh-toan (session-aware) phai gan
        // dung customer_id cua tai khoan dang dang nhap, khong chi dua vao
        // viec SDT nhap tren form co khop hay khong.
        User::factory()->create();
        $warehouse = Warehouse::factory()->create(['is_default' => true]);
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $customer = Customer::create([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($customer, 'customer');

        $response = $this->postJson('/thanh-toan', [
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'shipping_address' => '123 Test Street',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.customer_id', $customer->id);

        $ordersPage = $this->get('/tai-khoan/don-hang');
        $ordersPage->assertOk();
        $ordersPage->assertSee($response->json('data.code'));
    }

    public function test_logout_clears_customer_session(): void
    {
        $customer = Customer::create([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($customer, 'customer');
        $this->assertAuthenticated('customer');

        $this->post('/dang-xuat')->assertRedirect(route('storefront.home'));

        $this->assertGuest('customer');
    }
}
