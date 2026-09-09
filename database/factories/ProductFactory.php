<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'sku' => 'SKU-'.fake()->unique()->numberBetween(10000, 999999),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'category_id' => Category::factory(),
            'cost_price' => fake()->numberBetween(100000, 5000000),
            'sale_price' => fake()->numberBetween(150000, 6000000),
            'description' => fake()->sentence(),
            'is_serialized' => false,
            'is_active' => true,
        ];
    }

    public function serialized(): static
    {
        return $this->state(fn () => ['is_serialized' => true]);
    }
}
