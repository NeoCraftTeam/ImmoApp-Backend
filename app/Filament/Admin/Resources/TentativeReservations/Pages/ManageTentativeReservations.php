<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TentativeReservations\Pages;

use App\Filament\Admin\Resources\TentativeReservations\TentativeReservationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTentativeReservations extends ManageRecords
{
    protected static string $resource = TentativeReservationResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
