<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionTier: string
{
    case BASIC = 'basic';
    case PREMIUM = 'premium';
    case ENTERPRISE = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::BASIC => 'Basic',
            self::PREMIUM => 'Premium',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BASIC => 'Idéal pour les petites agences',
            self::PREMIUM => 'Pour les agences en croissance',
            self::ENTERPRISE => 'Solution complète pour grandes agences',
        };
    }

    /**
     * Get the ranking multiplier for this tier.
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::BASIC => 2.0,
            self::PREMIUM => 2.5,
            self::ENTERPRISE => 3.0,
        };
    }

    /**
     * Get maximum ads allowed for this tier.
     * Null means unlimited.
     */
    public function maxAds(): ?int
    {
        return match ($this) {
            self::BASIC => 10,
            self::PREMIUM => 50,
            self::ENTERPRISE => null, // Unlimited
        };
    }

    /**
     * Get boost duration in days for this tier.
     */
    public function boostDurationDays(): int
    {
        return match ($this) {
            self::BASIC => 7,
            self::PREMIUM => 14,
            self::ENTERPRISE => 30,
        };
    }

    /**
     * Check if tier includes priority support.
     */
    public function hasPrioritySupport(): bool
    {
        return match ($this) {
            self::BASIC => false,
            self::PREMIUM => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if tier includes analytics dashboard.
     */
    public function hasAnalytics(): bool
    {
        return match ($this) {
            self::BASIC => false,
            self::PREMIUM => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if tier includes API access.
     */
    public function hasApiAccess(): bool
    {
        return match ($this) {
            self::BASIC => false,
            self::PREMIUM => false,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Get all available features for this tier.
     *
     * @return array<string>
     */
    public function features(): array
    {
        $features = [
            'Annonces sponsorisées automatiquement',
            'Visibilité maximale dans les résultats',
            'Badge "Sponsorisé" sur toutes les annonces',
        ];

        if ($this->maxAds() === null) {
            $features[] = 'Annonces illimitées';
        } else {
            $features[] = "Jusqu'à {$this->maxAds()} annonces actives";
        }

        $features[] = "Boost de {$this->boostDurationDays()} jours par annonce";

        if ($this->hasPrioritySupport()) {
            $features[] = 'Support prioritaire';
        }

        if ($this->hasAnalytics()) {
            $features[] = 'Tableau de bord analytique';
            $features[] = 'Statistiques détaillées (vues, contacts, ROI)';
        }

        if ($this->hasApiAccess()) {
            $features[] = 'Accès API pour intégration';
            $features[] = 'Webhooks pour notifications';
        }

        return $features;
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::BASIC => 'blue',
            self::PREMIUM => 'purple',
            self::ENTERPRISE => 'gold',
        };
    }

    /**
     * Get sort order for display.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::BASIC => 1,
            self::PREMIUM => 2,
            self::ENTERPRISE => 3,
        };
    }
}
