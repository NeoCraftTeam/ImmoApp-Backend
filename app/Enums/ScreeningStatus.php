<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScreeningStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Submitted => 'Soumis',
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
            self::Expired => 'Expiré',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Expired]);
    }
}
