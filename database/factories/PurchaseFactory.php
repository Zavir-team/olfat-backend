<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'reference_no' => 'PUR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'supplier_id' => Supplier::factory(),
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'purchased_at' => now(),
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'posted',
            'notes' => null,
        ];
    }
}
