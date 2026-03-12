<?php

namespace App\Filament\Admin\Resources\PropertyAttributeCategories\Pages;

use App\Filament\Admin\Resources\PropertyAttributeCategories\PropertyAttributeCategoryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreatePropertyAttributeCategory extends CreateRecord
{
    protected static string $resource = PropertyAttributeCategoryResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Catégorie créée avec succès';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(PropertyAttributeCategoryResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }
}
