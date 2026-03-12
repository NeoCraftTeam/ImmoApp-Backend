<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdReportStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case REVIEWING = 'reviewing';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::REVIEWING => 'En cours',
            self::RESOLVED => 'Traite',
            self::DISMISSED => 'Classe sans suite',
        };
    }
}
