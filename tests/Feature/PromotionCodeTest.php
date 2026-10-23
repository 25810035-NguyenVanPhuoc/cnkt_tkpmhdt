<?php

namespace Tests\Feature;

use App\Enums\PromotionAppliesTo;
use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionCodeTest extends TestCase
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

    public function test_order_applies_promotion_code_discount(): void
    {
        // Backend ho tro promotion_code tu lau nhung khong co o nao o giao
        // dien de nhap - ca Admin POS lan Storefront checkout deu vua duoc
        // bo sung o nhap ma khuyen mai. Test nay xac nhan dung luong backend
        // that su giam gia va ghi nhan luot dung khi FE gui promotion_code.
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $promotion = Promotion::create([
            'name' => 'Giam 10%',
            'code' => 'TEST10',
            'type' => PromotionType::Percentage,
            'value' => 10,
            'applies_to' => PromotionAppliesTo::All,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'promotion_code' => 'TEST10',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', 200000);
        $response->assertJsonPath('data.discount_total', 20000);
        $response->assertJsonPath('data.grand_total', 180000);

        $this->assertDatabaseHas('order_promotion', [
            'promotion_id' => $promotion->id,
            'discount_amount' => 20000,
        ]);

        $promotion->refresh();
        $this->assertSame(1, $promotion->usage_count);
    }

    public function test_order_rejects_unknown_promotion_code(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100000]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $response = $this->postJson('/api/orders', [
            'warehouse_id' => $warehouse->id,
            'promotion_code' => 'KHONGTONTAI',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }
}
