<?php

namespace Database\Seeders;


use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        City::factory()->count(100)->create()->each(function (City $city) {
            Quarter::factory()->count(10)->for($city)->create();
        });
    }
}
