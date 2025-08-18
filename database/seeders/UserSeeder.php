<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdImage;
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

        User::factory()->customers()->count(600)->create();

        $agents = User::factory()->agents()->count(399)->create();

        $agents->each(function ($agent) {
            $ads = Ad::factory()->count(10)->for($agent)->create();
            $ads->each(function ($ad) {
                AdImage::factory()->count(5)->for($ad)->create();
                $customers = User::where('role', 'customer')->get();
                Review::factory()->count(10)->for($ad)->create([
                    'user_id' => $customers->random()->id,
                ]);

            });
        });


    }


}
