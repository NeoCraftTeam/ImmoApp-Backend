<?php

namespace App\Filament\Admin\Resources\AdImages\Pages;

use App\Filament\Admin\Resources\AdImages\AdImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdImages extends ManageRecords
{
    protected static string $resource = AdImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
