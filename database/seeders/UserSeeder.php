<?php

namespace Database\Seeders;

use App\Models\Ad;
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
        if (!User::where('email', 'admin@test.com')->exists()) {
            User::factory()
                ->admin()
                ->create(['email' => 'admin@test.com']);
        }

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
                // On utilise une image distante pour éviter l'erreur de fichier manquant sur le VPS
                try {
                    $ad->addMediaFromUrl('https://picsum.photos/seed/'.$ad->id.'/800/600')
                        ->toMediaCollection('images');
                } catch (\Exception $e) {
                    // Si pas d'internet ou erreur, on continue sans image au lieu de faire planter le seeder
                    \Log::warning("Impossible de charger l'image pour l'annonce {$ad->id}: ".$e->getMessage());
                }

                $customers = User::where('role', 'customer')->get();
                Review::factory()->count(3)->for($ad)->create([
                    'user_id' => $customers->random()->id,
                ]);

            });
        });

    }
}
