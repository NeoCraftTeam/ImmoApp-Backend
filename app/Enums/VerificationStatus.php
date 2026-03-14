<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VerificationStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Requested = 'requested';
    case InReview = 'in_review';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Non demandé',
            self::Requested => 'Demandé',
            self::InReview => 'En cours de vérification',
            self::Verified => 'Vérifié',
            self::Rejected => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Requested => 'warning',
            self::InReview => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
