<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads\Pages;

use App\Filament\Admin\Resources\Ads\AdResource;
use App\Models\Ad;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAds extends ManageRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => AdResource::mutateLocationMapData($data))
                ->using(fn (array $data, string $model): Ad => \Illuminate\Support\Facades\DB::transaction(fn () => $model::create($data))),
        ];
    }
}
