<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks\Pages;

use App\Filament\Admin\Resources\BoostPacks\BoostPackResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class EditBoostPack extends EditRecord
{
    protected static string $resource = BoostPackResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pack boost mis à jour.';
    }

    protected function afterSave(): void
    {
        Cache::forget('boost:packs:active');
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
            DeleteAction::make()
                ->after(fn () => Cache::forget('boost:packs:active')),
        ];
    }
}
