<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Ads\Pages;

use App\Filament\Agency\Resources\Ads\AdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAds extends ManageRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => AdResource::mutateLocationMapData($data)),
        ];
    }
}
