<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Geographic data now comes from the OSM pipeline. Import and synchronize
     * it before running MassiveAdSeeder explicitly.
     */
    public function run(): void
    {
        $this->call([
            AdTypeSeeder::class,
            SubscriptionPlanSeeder::class,
            PointSystemSeeder::class,
            BoostPackSeeder::class,
            PropertyAttributeSeeder::class,
            SurveySeeder::class,
        ]);

        if (City::query()->exists() && Quarter::query()->exists()) {
            $this->call(MassiveAdSeeder::class);
        } else {
            $this->command->warn('Données géographiques absentes : lancez php artisan geo:refresh-osm cameroon avant MassiveAdSeeder.');
        }
    }
}
