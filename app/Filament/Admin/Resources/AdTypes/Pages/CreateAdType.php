<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdTypes\Pages;

use App\Filament\Admin\Resources\AdTypes\AdTypeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateAdType extends CreateRecord
{
    protected static string $resource = AdTypeResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Type d\'annonce créé avec succès';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(AdTypeResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }
}
