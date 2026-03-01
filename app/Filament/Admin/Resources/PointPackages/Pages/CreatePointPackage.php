<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Pages;

use App\Filament\Admin\Resources\PointPackages\PointPackageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreatePointPackage extends CreateRecord
{
    protected static string $resource = PointPackageResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pack de crédits créé avec succès';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(PointPackageResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }
}
