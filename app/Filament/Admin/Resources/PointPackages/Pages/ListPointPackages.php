<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Pages;

use App\Filament\Admin\Resources\PointPackages\PointPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPointPackages extends ListRecords
{
    protected static string $resource = PointPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Pack de crédits créé avec succès'),
        ];
    }
}
