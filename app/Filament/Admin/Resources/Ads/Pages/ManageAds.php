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
                    if (isset($data['location_map']) && is_array($data['location_map'])) {
                        $data['location'] = Point::make($data['location_map']['lat'], $data['location_map']['lng']);
                        unset($data['location_map']);
                    }

                    return $data;
                })
                ->using(fn (array $data, string $model): Ad => \Illuminate\Support\Facades\DB::transaction(fn () => $model::create($data))),
        ];
    }
}
