<?php

namespace App\Filament\Admin\Resources\PropertyAttributes\Pages;

use App\Filament\Admin\Resources\PropertyAttributes\PropertyAttributeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreatePropertyAttribute extends CreateRecord
{
    protected static string $resource = PropertyAttributeResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Attribut créé avec succès';
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
        ];
    }
}
