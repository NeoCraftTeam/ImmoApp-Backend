<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Viewings\Pages;

use App\Filament\Bailleur\Resources\Viewings\ViewingReservationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageViewingReservations extends ManageRecords
{
    protected static string $resource = ViewingReservationResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
