<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACTIVE => 'Actif',
            self::EXPIRED => 'Expiré',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::EXPIRED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
