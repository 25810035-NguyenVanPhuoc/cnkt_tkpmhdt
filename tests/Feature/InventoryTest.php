<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo(['warehouse.view', 'warehouse.create', 'warehouse.update']);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_manual_stock_in_creates_movement_and_increases_quantity(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/stock/in', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
        ]);

        $response->assertOk();
        $this->assertSame(20, ProductStock::where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 20,
        ]);
    }

    public function test_adjustment_updates_quantity_and_logs_correct_delta(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $response = $this->postJson('/api/stock/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_after' => 7,
        ]);

        $response->assertOk();
        $this->assertSame(7, ProductStock::where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -3,
        ]);
    }

    public function test_transfer_creates_two_movement_rows_with_opposite_signs(): void
    {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create();
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 10]);

        $response = $this->postJson('/api/stock/transfer', [
            'product_id' => $product->id,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'quantity' => 4,
        ]);

        $response->assertOk();
        $this->assertSame(6, ProductStock::where('product_id', $product->id)->where('warehouse_id', $from->id)->value('quantity'));
        $this->assertSame(4, ProductStock::where('product_id', $product->id)->where('warehouse_id', $to->id)->value('quantity'));

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'type' => 'transfer', 'quantity' => -4]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $to->id, 'type' => 'transfer', 'quantity' => 4]);
    }
}
