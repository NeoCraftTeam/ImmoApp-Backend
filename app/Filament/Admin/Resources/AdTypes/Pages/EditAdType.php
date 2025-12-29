<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdTypes\Pages;

use App\Filament\Admin\Resources\AdTypes\AdTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAdType extends EditRecord
{
    protected static string $resource = AdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
