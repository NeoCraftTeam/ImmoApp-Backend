<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case INDIVIDUAL = 'individual';
    case AGENCY = 'agency';

    public function getLabel(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Particulier',
            self::AGENCY => 'Agence',
        };
    }
}
