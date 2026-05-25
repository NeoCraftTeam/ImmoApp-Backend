<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DisputeType: string implements HasLabel
{
    case DEPOSIT = 'deposit';
    case REPAIR = 'repair';
    case LEASE_TERMINATION = 'lease_termination';
    case PAYMENT = 'payment';
    case ACCESS_REFUSED = 'access_refused';
    case MISREPRESENTATION = 'misrepresentation';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Caution / dépôt de garantie',
            self::REPAIR => 'Réparations / habitabilité',
            self::LEASE_TERMINATION => 'Résiliation du bail',
            self::PAYMENT => 'Paiement / impayé',
            self::ACCESS_REFUSED => 'Accès au logement refusé',
            self::MISREPRESENTATION => 'Annonce non conforme',
            self::OTHER => 'Autre',
        };
    }
}
