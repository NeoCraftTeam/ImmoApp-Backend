<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads\Pages;

use App\Filament\Admin\Resources\Ads\AdResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAd extends EditRecord
{
    protected static string $resource = AdResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Annonce mise à jour';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(AdResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
            ViewAction::make(),
            DeleteAction::make()->successNotificationTitle('Annonce supprimée'),
            ForceDeleteAction::make()->successNotificationTitle('Annonce supprimée définitivement'),
            RestoreAction::make()->successNotificationTitle('Annonce restaurée'),
        ];
    }

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AdResource::mutateLocationMapData($data);
    }
}
