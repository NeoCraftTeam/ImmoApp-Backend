<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case CUSTOMER = 'customer';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::AGENT => 'Agent Immobilier',
            self::CUSTOMER => 'Client',
        };
    }
}
