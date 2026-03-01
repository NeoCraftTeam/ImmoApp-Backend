<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PointPackage;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class PointSystemSeeder extends Seeder
{
    public function run(): void
    {
        // --- Default credit packages ---
        $packages = [
            [
                'name' => 'Pack Starter',
                'description' => 'Idéal pour découvrir la plateforme',
                'badge' => null,
                'price' => 1000,   // 1 000 FCFA
                'points_awarded' => 10,
                'features' => [
                    '10 crédits de déblocage',
                    'Accès aux contacts propriétaires',
                    'Support par email',
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pack Pro',
                'description' => 'Le choix préféré de nos utilisateurs',
                'badge' => 'Le + populaire',
                'price' => 4000,   // 4 000 FCFA
                'points_awarded' => 50,
                'features' => [
                    '50 crédits de déblocage',
                    'Accès aux contacts propriétaires',
                    'Support prioritaire',
                    'Économisez 20%',
                ],
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pack Premium',
                'description' => 'Pour les professionnels de l\'immobilier',
                'badge' => 'Meilleur rapport',
                'price' => 7000,   // 7 000 FCFA
                'points_awarded' => 120,
                'features' => [
                    '120 crédits de déblocage',
                    'Accès aux contacts propriétaires',
                    'Support prioritaire 24h/7j',
                    'Économisez 40%',
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            PointPackage::updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }

        // --- Default system settings ---
        Setting::set('unlock_cost_points', 2, 'Coût en crédits pour débloquer une annonce', 'credits');
        Setting::set('welcome_bonus_points', 5, 'Bonus de bienvenue pour les nouveaux utilisateurs', 'credits');

        $this->command->info('✅ Système de crédits initialisé avec succès!');
    }
}
