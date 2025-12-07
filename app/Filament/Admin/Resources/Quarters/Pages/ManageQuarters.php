<?php

namespace App\Filament\Admin\Resources\Quarters\Pages;

use App\Filament\Admin\Resources\Quarters\QuarterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuarters extends ManageRecords
{
    protected static string $resource = QuarterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
