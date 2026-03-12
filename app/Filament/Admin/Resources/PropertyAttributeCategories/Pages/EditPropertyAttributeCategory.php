<?php

namespace App\Filament\Admin\Resources\PropertyAttributeCategories\Pages;

use App\Filament\Admin\Resources\PropertyAttributeCategories\PropertyAttributeCategoryResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPropertyAttributeCategory extends EditRecord
{
    protected static string $resource = PropertyAttributeCategoryResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Catégorie mise à jour';
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
            DeleteAction::make()->successNotificationTitle('Catégorie supprimée'),
        ];
    }
}
