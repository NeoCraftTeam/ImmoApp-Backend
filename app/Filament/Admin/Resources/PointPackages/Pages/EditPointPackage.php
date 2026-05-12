<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Pages;

use App\Filament\Admin\Resources\PointPackages\PointPackageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPointPackage extends EditRecord
{
    protected static string $resource = PointPackageResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pack de crédits mis à jour';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(PointPackageResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
            DeleteAction::make()->successNotificationTitle('Pack de crédits supprimé'),
        ];
    }
}
