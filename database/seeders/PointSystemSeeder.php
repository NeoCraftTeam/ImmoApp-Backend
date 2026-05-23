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
                'description' => 'Trouvez votre premier logement — 10 contacts propriétaires directs, sans intermédiaire',
                'badge' => null,
                'price' => 1000,   // 1 000 FCFA
                'points_awarded' => 10,
                'features' => [
                    '10 déverrouillages de contacts (100 FCFA/contact)',
                    'Numéros & WhatsApp directs',
                    'Aucune commission, aucun frais caché',
                    'Valable 6 mois',
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pack Pro',
                'description' => 'Cherchez sans vous limiter — 50 contacts au meilleur prix, -20% vs Starter',
                'badge' => 'Le + populaire',
                'price' => 4000,   // 4 000 FCFA
                'points_awarded' => 50,
                'features' => [
                    '50 déverrouillages de contacts (80 FCFA/contact)',
                    'Numéros & WhatsApp directs',
                    '-20% par contact vs Pack Starter',
                    'Support prioritaire',
                    'Valable 6 mois',
                ],
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pack Premium',
                'description' => 'Pour les familles et les professionnels en mobilité — 120 contacts, -42% vs Starter',
                'badge' => 'Meilleur rapport',
                'price' => 7000,   // 7 000 FCFA
                'points_awarded' => 120,
                'features' => [
                    '120 déverrouillages de contacts (~58 FCFA/contact)',
                    'Numéros & WhatsApp directs',
                    '-42% par contact vs Pack Starter',
                    'Support prioritaire 24h/7j',
                    'Valable 12 mois',
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
