<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Ads\Pages;

use App\Filament\Agency\Resources\Ads\AdResource;
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
                    if (isset($data['location_map']) && is_array($data['location_map'])) {
                        $data['location'] = Point::make($data['location_map']['lat'], $data['location_map']['lng']);
                        unset($data['location_map']);
                    }

                    return $data;
                }),
        ];
    }
}
