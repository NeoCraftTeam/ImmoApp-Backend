<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
            self::Expired => 'Expirée',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed => true,
            default => false,
        };
    }
}
