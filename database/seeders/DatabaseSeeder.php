<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Optional seeders (not called by default): UserSeeder, AgencySeeder,
     * PaymentSeeder, CitySeeder. Call them explicitly if needed.
     */
    public function run(): void
    {
        $this->call([
            AdTypeSeeder::class,
            SubscriptionPlanSeeder::class,
            PointSystemSeeder::class,
            BoostPackSeeder::class,
            CameroonCitiesSeeder::class,
            PropertyAttributeSeeder::class,
            MassiveAdSeeder::class,
            SurveySeeder::class,
        ]);
    }
}
