<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdReportReason: string implements HasLabel
{
    case INACCURATE = 'inaccurate';
    case NOT_REAL_PROPERTY = 'not_real_property';
    case SCAM = 'scam';
    case SHOCKING_CONTENT = 'shocking_content';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::INACCURATE => 'Annonce inexacte ou incorrecte',
            self::NOT_REAL_PROPERTY => 'Ce n\'est pas un veritable logement',
            self::SCAM => 'Il s\'agit d\'une arnaque',
            self::SHOCKING_CONTENT => 'Contenu choquant',
            self::OTHER => 'Autre motif',
        };
    }
}
