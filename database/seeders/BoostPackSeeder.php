<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoostPack;
use Illuminate\Database\Seeder;

class BoostPackSeeder extends Seeder
{
    public function run(): void
    {
        $packs = [
            [
                'name' => 'Pack Starter',
                'slug' => 'pack-starter',
                'description' => 'Idéal pour donner un coup de pouce à votre annonce. Votre bien apparaît en tête des résultats pendant 7 jours.',
                'reach_description' => 'Touche jusqu\'à 500 personnes',
                'duration_days' => 7,
                'boost_score' => 30,
                'price_credits' => 10,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pack Pro',
                'slug' => 'pack-pro',
                'description' => 'Pour maximiser la visibilité de votre annonce. Mise en avant prioritaire sur le fil principal et les résultats de recherche pendant 7 jours.',
                'reach_description' => 'Touche jusqu\'à 2 000 personnes',
                'duration_days' => 7,
                'boost_score' => 60,
                'price_credits' => 20,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pack Premium',
                'slug' => 'pack-premium',
                'description' => 'Visibilité maximale pendant tout un mois. Votre annonce reste en tête du fil, des recherches et des recommandations pendant 30 jours.',
                'reach_description' => 'Touche jusqu\'à 10 000 personnes',
                'duration_days' => 30,
                'boost_score' => 100,
                'price_credits' => 60,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($packs as $pack) {
            BoostPack::updateOrCreate(['slug' => $pack['slug']], $pack);
        }
    }
}
