<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdReportScamReason: string implements HasLabel
{
    case ASKED_OFF_PLATFORM_PAYMENT = 'asked_off_platform_payment';
    case SHARED_CONTACTS = 'shared_contacts';
    case PROMOTING_EXTERNAL_SERVICES = 'promoting_external_services';
    case DUPLICATE_LISTING = 'duplicate_listing';
    case MISLEADING_LISTING = 'misleading_listing';

    public function getLabel(): string
    {
        return match ($this) {
            self::ASKED_OFF_PLATFORM_PAYMENT => 'Demande de paiement hors plateforme',
            self::SHARED_CONTACTS => 'Partage de coordonnees personnelles',
            self::PROMOTING_EXTERNAL_SERVICES => 'Publicite pour des services externes',
            self::DUPLICATE_LISTING => 'Annonce en double',
            self::MISLEADING_LISTING => 'Annonce trompeuse',
        };
    }
}
