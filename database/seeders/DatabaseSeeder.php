<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->call([
                LocationSeeder::class,
                SettingSeeder::class,
                UserSeeder::class,
            ]);

            return;
        }

        $this->call([
            LocationSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
