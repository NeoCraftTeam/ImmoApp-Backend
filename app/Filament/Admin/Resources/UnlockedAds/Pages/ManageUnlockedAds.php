<?php

namespace App\Filament\Admin\Resources\UnlockedAds\Pages;

use App\Filament\Admin\Resources\UnlockedAds\UnlockedAdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnlockedAds extends ManageRecords
{
    protected static string $resource = UnlockedAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
