<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->optional()->numerify('07########'),
            'address' => fake()->optional()->address(),
            'notes' => null,
            'balance' => 0,
            'is_active' => true,
        ];
    }
}
