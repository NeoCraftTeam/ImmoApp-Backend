<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Pages;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Agence mise à jour';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(AgencyResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
            ViewAction::make(),
            DeleteAction::make()->successNotificationTitle('Agence supprimée'),
            ForceDeleteAction::make()->successNotificationTitle('Agence supprimée définitivement'),
            RestoreAction::make()->successNotificationTitle('Agence restaurée'),
        ];
    }
}
