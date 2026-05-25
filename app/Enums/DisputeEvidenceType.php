<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DisputeEvidenceType: string implements HasLabel
{
    case PHOTO = 'photo';
    case DOCUMENT = 'document';
    case SCREENSHOT = 'screenshot';
    case CONTRACT = 'contract';
    case RECEIPT = 'receipt';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PHOTO => 'Photo',
            self::DOCUMENT => 'Document',
            self::SCREENSHOT => 'Capture d\'écran',
            self::CONTRACT => 'Contrat',
            self::RECEIPT => 'Reçu / facture',
            self::OTHER => 'Autre',
        };
    }
}
