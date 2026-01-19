<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads\Pages;

use App\Filament\Admin\Resources\Ads\AdResource;
use App\Models\Ad;
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
                })
                ->using(function (array $data, string $model): Ad {
                    return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $model) {
                        return $model::create($data);
                    });
                }),
        ];
    }
}
