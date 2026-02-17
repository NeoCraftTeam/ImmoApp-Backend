<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Plan de base pour les petites agences. Boost modéré de vos annonces.',
                'price' => 15000, // 15,000 FCFA/mois
                'price_yearly' => 150000, // 150,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 10,
                'boost_duration_days' => 7,
                'max_ads' => 20,
                'features' => [
                    'Jusqu\'à 20 annonces par mois',
                    'Boost de +10 points pendant 7 jours',
                    'Support par email',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Plan premium pour les agences en croissance. Boost puissant et plus d\'annonces.',
                'price' => 35000, // 35,000 FCFA/mois
                'price_yearly' => 350000, // 350,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 25,
                'boost_duration_days' => 14,
                'max_ads' => 50,
                'features' => [
                    'Jusqu\'à 50 annonces par mois',
                    'Boost de +25 points pendant 14 jours',
                    'Badge "Agence Premium"',
                    'Support prioritaire',
                    'Statistiques avancées',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Plan entreprise pour les grandes agences. Boost maximum et annonces illimitées.',
                'price' => 75000, // 75,000 FCFA/mois
                'price_yearly' => 750000, // 750,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 50,
                'boost_duration_days' => 30,
                'max_ads' => null, // Illimité
                'features' => [
                    'Annonces illimitées',
                    'Boost de +50 points pendant 30 jours',
                    'Badge "Agence Elite"',
                    'Support 24/7',
                    'Statistiques avancées',
                    'API dédiée',
                    'Gestionnaire de compte dédié',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('✅ Plans d\'abonnement créés avec succès!');
    }
}
