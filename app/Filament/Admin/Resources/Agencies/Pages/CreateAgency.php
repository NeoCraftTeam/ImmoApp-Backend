<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Pages;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    #[\Override]
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => static::getModel()::create($data));
    }
}
