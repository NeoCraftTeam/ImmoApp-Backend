<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Pages;

use App\Filament\Admin\Resources\PointPackages\PointPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPointPackage extends EditRecord
{
    protected static string $resource = PointPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
