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
        // unlock_cost_points = 2  →  contacts débloqués = points_awarded / 2
        // Starter : 10 crédits / 2 =  5 contacts →  200 FCFA/contact
        // Pro     : 50 crédits / 2 = 25 contacts →  160 FCFA/contact (-20 %)
        // Premium :120 crédits / 2 = 60 contacts → ~117 FCFA/contact (-42 %)
        $packages = [
            [
                'name' => 'Pack Starter',
                'description' => 'Testez KeyHome sans risque. Contactez 5 propriétaires vérifiés — numéro et WhatsApp inclus — sans passer par une agence.',
                'badge' => null,
                'price' => 1000,   // 1 000 FCFA
                'points_awarded' => 10,
                'features' => [
                    '5 annonces propriétaires débloqués',
                    'Appelez ou WhatsApp le propriétaire directement',
                    'Annonces vérifiées — zéro commission, zéro frais cachés',
                    '200 FCFA/contact · Crédits valables 6 mois',
                ],
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pack Pro',
                'description' => 'Le choix des chercheurs actifs. 25 propriétaires en accès direct — assez pour trouver votre logement avant vos concurrents.',
                'badge' => 'Le + populaire',
                'price' => 4000,   // 4 000 FCFA
                'points_awarded' => 50,
                'features' => [
                    '25 contacts propriétaires débloqués',
                    '160 FCFA/contact — économisez 20 % vs Starter',
                    'Appel + WhatsApp + messagerie KeyHome inclus',
                    'Support prioritaire — réponse rapide garantie',
                    'Crédits valables 6 mois',
                ],
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pack Premium',
                'description' => 'Pour les familles ambitieuses et les professionnels en mobilité. 60 contacts sur 12 mois — le meilleur coût par logement trouvé.',
                'badge' => 'Meilleur rapport',
                'price' => 7000,   // 7 000 FCFA
                'points_awarded' => 120,
                'features' => [
                    '60 contacts propriétaires débloqués',
                    '~117 FCFA/contact — 42 % de réduction vs Starter',
                    'Appel + WhatsApp + messagerie KeyHome inclus',
                    'Support prioritaire 24h/7j',
                    'Crédits valables 12 mois · Idéal agences & équipes RH',
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
