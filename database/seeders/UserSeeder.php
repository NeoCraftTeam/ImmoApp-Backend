<?php

namespace Database\Seeders;

use App\Models\Ad;
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
        // No admin users — only customers and agents
        User::factory()->customers()->count(50)->create();

        $agencyAgents = User::factory()->agents()->state(['type' => 'agency'])->count(5)->create();
        $individualAgents = User::factory()->agents()->state(['type' => 'individual'])->count(5)->create();

        $agents = $agencyAgents->merge($individualAgents);

        $agents->each(function ($agent) {
            $ads = Ad::factory()->count(5)->for($agent)->create();

            $ads->each(function ($ad) {
                for ($i = 0; $i < 7; $i++) {
                    try {
                        $ad->addMediaFromUrl('https://picsum.photos/seed/'.substr($ad->id, 0, 8).'-'.$i.'/1200/800')
                            ->usingName('Photo '.($i + 1))
                            ->toMediaCollection('images');
                    } catch (\Exception $e) {
                        \Log::warning("Image non chargée pour l'annonce {$ad->id}: ".$e->getMessage());
                    }
                }

                $customers = User::where('role', 'customer')->inRandomOrder()->take(10)->get();
                $reviewCount = mt_rand(3, 10);
                $usedIds = [];

                for ($r = 0; $r < $reviewCount; $r++) {
                    $customer = $customers->whereNotIn('id', $usedIds)->first();
                    if (!$customer) {
                        break;
                    }
                    $usedIds[] = $customer->id;
                    Review::factory()->for($ad)->create(['user_id' => $customer->id]);
                }
            });
        });
    }
}
