<?php

namespace App\Filament\Admin\Resources\Ads\Pages;

use App\Filament\Admin\Resources\Ads\AdResource;
use Clickbar\Magellan\Data\Geometries\Point;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAds extends ManageRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    if (isset($data['latitude']) && isset($data['longitude'])) {
                        $data['location'] = Point::make($data['latitude'], $data['longitude']);
                        unset($data['latitude'], $data['longitude']);
                    }

                    return $data;
                }),
        ];
    }
}
