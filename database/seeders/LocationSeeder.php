<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Kabul', 'code' => 'kabul'],
            ['name' => 'Maidan/Wardak', 'code' => 'maidan-wardak'],
            ['name' => 'Ghazni', 'code' => 'ghazni'],
        ];

        foreach ($locations as $location) {
            Location::query()->updateOrCreate(
                ['code' => $location['code']],
                [
                    'name' => $location['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
