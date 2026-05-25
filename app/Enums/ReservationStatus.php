<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
            self::Expired => 'Expirée',
            self::Completed => 'Terminée',
            self::NoShow => 'Absent',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Expired, self::Completed, self::NoShow => true,
            default => false,
        };
    }
}
