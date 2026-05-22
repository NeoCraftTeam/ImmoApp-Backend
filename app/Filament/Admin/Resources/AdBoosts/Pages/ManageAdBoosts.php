<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdBoosts\Pages;

use App\Filament\Admin\Resources\AdBoosts\AdBoostResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAdBoosts extends ManageRecords
{
    protected static string $resource = AdBoostResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
