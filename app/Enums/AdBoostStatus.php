<?php

declare(strict_types=1);

namespace App\Enums;

enum AdBoostStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Cancelled => 'Annulé',
        };
    }
}
