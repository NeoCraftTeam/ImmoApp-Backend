<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdTypes\Pages;

use App\Filament\Admin\Resources\AdTypes\AdTypeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAdType extends EditRecord
{
    protected static string $resource = AdTypeResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Type d\'annonce mis à jour';
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
            DeleteAction::make()->successNotificationTitle('Type d\'annonce supprimé'),
            ForceDeleteAction::make()->successNotificationTitle('Type d\'annonce supprimé définitivement'),
            RestoreAction::make()->successNotificationTitle('Type d\'annonce restauré'),
        ];
    }
}
