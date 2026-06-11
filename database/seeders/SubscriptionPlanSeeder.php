<?php

namespace Database\Seeders;

use App\Enums\SubscriptionTier;
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
                'tier' => SubscriptionTier::BASIC,
                'tier_multiplier' => SubscriptionTier::BASIC->multiplier(),
                'description' => 'Idéal pour les petites agences qui démarrent',
                'price' => 15000, // 15,000 FCFA/mois
                'price_yearly' => 150000, // 150,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 10,
                'boost_duration_days' => SubscriptionTier::BASIC->boostDurationDays(),
                'max_ads' => SubscriptionTier::BASIC->maxAds(),
                'features' => SubscriptionTier::BASIC->features(),
                'is_active' => true,
                'has_trial' => true,
                'trial_days' => 7,
                'has_priority_support' => false,
                'has_analytics' => false,
                'has_api_access' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'tier' => SubscriptionTier::PREMIUM,
                'tier_multiplier' => SubscriptionTier::PREMIUM->multiplier(),
                'description' => 'Pour les agences en croissance qui veulent plus de visibilité',
                'price' => 35000, // 35,000 FCFA/mois
                'price_yearly' => 350000, // 350,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 25,
                'boost_duration_days' => SubscriptionTier::PREMIUM->boostDurationDays(),
                'max_ads' => SubscriptionTier::PREMIUM->maxAds(),
                'features' => SubscriptionTier::PREMIUM->features(),
                'is_active' => true,
                'has_trial' => true,
                'trial_days' => 14,
                'has_priority_support' => true,
                'has_analytics' => true,
                'has_api_access' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'tier' => SubscriptionTier::ENTERPRISE,
                'tier_multiplier' => SubscriptionTier::ENTERPRISE->multiplier(),
                'description' => 'Solution complète pour les grandes agences immobilières',
                'price' => 75000, // 75,000 FCFA/mois
                'price_yearly' => 750000, // 750,000 FCFA/an (~2 mois offerts)
                'duration_days' => 30,
                'boost_score' => 50,
                'boost_duration_days' => SubscriptionTier::ENTERPRISE->boostDurationDays(),
                'max_ads' => null, // Unlimited
                'features' => SubscriptionTier::ENTERPRISE->features(),
                'is_active' => true,
                'has_trial' => true,
                'trial_days' => 30,
                'has_priority_support' => true,
                'has_analytics' => true,
                'has_api_access' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('✅ 3 plans d\'abonnement créés avec succès!');
        $this->command->info('   - Basic: 15,000 FCFA/mois (2.0x multiplier)');
        $this->command->info('   - Premium: 35,000 FCFA/mois (2.5x multiplier)');
        $this->command->info('   - Enterprise: 75,000 FCFA/mois (3.0x multiplier)');
    }
}
