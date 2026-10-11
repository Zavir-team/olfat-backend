<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['20kg', '30kg', '50kg', '6 pieces']),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'base_unit' => 'kg',
            'package_qty' => 20,
            'package_unit' => 'kg',
            'is_active' => true,
        ];
    }
}
