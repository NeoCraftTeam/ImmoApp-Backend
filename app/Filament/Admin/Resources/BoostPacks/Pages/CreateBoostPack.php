<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks\Pages;

use App\Filament\Admin\Resources\BoostPacks\BoostPackResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateBoostPack extends CreateRecord
{
    protected static string $resource = BoostPackResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pack boost créé avec succès.';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(BoostPackResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }
}
