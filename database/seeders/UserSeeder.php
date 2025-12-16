<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Agency;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
            ->admin()
            ->create(['email' => 'admin@test.com']);

        User::factory()->customers()->count(50)->create();

        // Create 5 agents of type 'agency'
        $agencyAgents = User::factory()->agents()->state(['type' => 'agency'])->count(5)->create();

        // Create 5 agents of type 'individual'
        $individualAgents = User::factory()->agents()->state(['type' => 'individual'])->count(5)->create();

        $agents = $agencyAgents->merge($individualAgents);

        $agents->each(function ($agent) {
            // Agency creation moved to AgencySeeder

            $ads = Ad::factory()->count(5)->for($agent)->create();
            $ads->each(function ($ad) {
                AdImage::factory()->count(3)->for($ad)->create();
                $customers = User::where('role', 'customer')->get();
                Review::factory()->count(3)->for($ad)->create([
                    'user_id' => $customers->random()->id,
                ]);

            });
        });

    }
}
