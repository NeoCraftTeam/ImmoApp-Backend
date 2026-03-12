<?php

namespace App\Filament\Admin\Resources\PropertyAttributeCategories\Pages;

use App\Filament\Admin\Resources\PropertyAttributeCategories\PropertyAttributeCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPropertyAttributeCategories extends ListRecords
{
    protected static string $resource = PropertyAttributeCategoryResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Catégorie créée avec succès'),
        ];
    }
}
