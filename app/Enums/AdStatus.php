<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdStatus: string implements HasLabel
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case RENT = 'rent';
    case PENDING = 'pending';
    case SOLD = 'sold';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::RESERVED => 'Reserved',
            self::RENT => 'Rent',
            self::PENDING => 'Pending',
            self::SOLD => 'Sold',
        };
    }
}
