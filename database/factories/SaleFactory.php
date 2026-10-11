<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'reference_no' => 'SAL-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'customer_id' => Customer::factory(),
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'sold_at' => now(),
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payment_type' => 'cash',
            'status' => 'posted',
            'notes' => null,
        ];
    }
}
