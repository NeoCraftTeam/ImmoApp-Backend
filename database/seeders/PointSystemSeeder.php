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
        // --- Default point packages ---
        $packages = [
            [
                'name' => 'Pack Starter',
                'price' => 1000,   // 1 000 FCFA
                'points_awarded' => 10,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pack Pro',
                'price' => 4000,   // 4 000 FCFA
                'points_awarded' => 50,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pack Premium',
                'price' => 7000,   // 7 000 FCFA
                'points_awarded' => 120,
                'is_active' => true,
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
        Setting::set('unlock_cost_points', 2, 'Coût en points pour débloquer une annonce', 'points');
        Setting::set('welcome_bonus_points', 5, 'Bonus de bienvenue pour les nouveaux utilisateurs', 'points');

        $this->command->info('✅ Système de points initialisé avec succès!');
    }
}
