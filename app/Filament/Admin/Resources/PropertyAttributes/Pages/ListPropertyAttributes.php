<?php

namespace App\Filament\Admin\Resources\PropertyAttributes\Pages;

use App\Filament\Admin\Resources\PropertyAttributes\PropertyAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPropertyAttributes extends ListRecords
{
    protected static string $resource = PropertyAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
