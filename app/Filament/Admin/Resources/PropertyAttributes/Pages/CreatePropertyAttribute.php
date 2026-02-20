<?php

namespace App\Filament\Admin\Resources\PropertyAttributes\Pages;

use App\Filament\Admin\Resources\PropertyAttributes\PropertyAttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePropertyAttribute extends CreateRecord
{
    protected static string $resource = PropertyAttributeResource::class;
}
