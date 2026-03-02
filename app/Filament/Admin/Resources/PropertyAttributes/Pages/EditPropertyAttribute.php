<?php

namespace App\Filament\Admin\Resources\PropertyAttributes\Pages;

use App\Filament\Admin\Resources\PropertyAttributes\PropertyAttributeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPropertyAttribute extends EditRecord
{
    protected static string $resource = PropertyAttributeResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Attribut mis à jour';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(PropertyAttributeResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
            DeleteAction::make()->successNotificationTitle('Attribut supprimé'),
        ];
    }
}
