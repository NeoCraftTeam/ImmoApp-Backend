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
                'description' => 'Donnez un coup de pouce à votre annonce — passez en tête des résultats pendant 7 jours et multipliez vos visites.',
                'reach_description' => 'Jusqu\'à 500 locataires potentiels',
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
                'description' => 'Visibilité prioritaire sur le fil principal et les recherches pendant 7 jours — 4× plus de portée que le Starter.',
                'reach_description' => 'Jusqu\'à 2 000 locataires potentiels',
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
                'description' => 'Louez en moins de 30 jours — un mois entier en tête du fil, des recherches et des recommandations KeyHome.',
                'reach_description' => 'Jusqu\'à 10 000 locataires potentiels',
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
