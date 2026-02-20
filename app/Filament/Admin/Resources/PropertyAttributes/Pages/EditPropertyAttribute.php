<?php

namespace App\Filament\Admin\Resources\PropertyAttributes\Pages;

use App\Filament\Admin\Resources\PropertyAttributes\PropertyAttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPropertyAttribute extends EditRecord
{
    protected static string $resource = PropertyAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
