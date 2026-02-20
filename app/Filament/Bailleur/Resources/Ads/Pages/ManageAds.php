<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Ads\Pages;

use App\Enums\AdStatus;
use App\Filament\Bailleur\Resources\Ads\AdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageAds extends ManageRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth(Width::FourExtraLarge)
                ->mutateFormDataUsing(function (array $data): array {
                    $data = AdResource::mutateLocationMapData($data);
                    $data['status'] ??= AdStatus::PENDING->value;

                    return $data;
                }),
        ];
    }
}
