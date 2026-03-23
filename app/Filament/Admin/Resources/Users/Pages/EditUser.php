<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Utilisateur mis à jour';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(UserResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
            ViewAction::make(),
            DeleteAction::make()->successNotificationTitle('Utilisateur supprimé'),
            ForceDeleteAction::make()->successNotificationTitle('Utilisateur supprimé définitivement'),
            RestoreAction::make()->successNotificationTitle('Utilisateur restauré'),
        ];
    }
}
