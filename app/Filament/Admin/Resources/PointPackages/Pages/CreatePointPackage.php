<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Pages;

use App\Filament\Admin\Resources\PointPackages\PointPackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePointPackage extends CreateRecord
{
    protected static string $resource = PointPackageResource::class;
}
