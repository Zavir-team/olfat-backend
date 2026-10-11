<?php

namespace Database\Factories;

use App\Models\ExternalParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExternalParty> */
class ExternalPartyFactory extends Factory
{
    protected $model = ExternalParty::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->optional()->numerify('07########'),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
