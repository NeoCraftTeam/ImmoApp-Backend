<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks\Pages;

use App\Filament\Admin\Resources\BoostPacks\BoostPackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBoostPacks extends ListRecords
{
    protected static string $resource = BoostPackResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
