<?php

namespace Tests\Feature;

use App\Enums\ProductUnitStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo(['sales.view', 'sales.create', 'sales.update']);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_creating_order_deducts_product_stock_quantity(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $response = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated();
        $this->assertSame(7, ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'out',
            'quantity' => -3,
        ]);
    }

    public function test_creating_order_reserves_serialized_units(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->serialized()->create(['sale_price' => 5000000]);
        $unit1 = ProductUnit::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'imei_serial' => 'IMEI-1', 'status' => ProductUnitStatus::InStock]);
        $unit2 = ProductUnit::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'imei_serial' => 'IMEI-2', 'status' => ProductUnitStatus::InStock]);

        $response = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'product_unit_ids' => [$unit1->id, $unit2->id]],
            ],
        ]);

        $response->assertCreated();
        $this->assertSame('reserved', $unit1->refresh()->status->value);
        $this->assertSame('reserved', $unit2->refresh()->status->value);
        $this->assertNotNull($unit1->order_item_id);
    }

    public function test_order_creation_fails_when_stock_insufficient(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]);

        $response = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, ProductStock::where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cancel_order_restores_stock_and_units(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $created = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])->json('data');

        $this->assertSame(6, ProductStock::where('product_id', $product->id)->value('quantity'));

        $response = $this->postJson("/api/orders/{$created['id']}/cancel");

        $response->assertOk();
        $this->assertSame(10, ProductStock::where('product_id', $product->id)->value('quantity'));
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $order = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->json('data');

        $orderId = $order['id'];

        $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'confirmed'])->assertOk();
        $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'delivering'])->assertOk();
        $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'completed'])->assertOk();

        $response = $this->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(422);
    }
}
