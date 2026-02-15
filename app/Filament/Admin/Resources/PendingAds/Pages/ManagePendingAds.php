<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingAds\Pages;

use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePendingAds extends ManageRecords
{
    protected static string $resource = PendingAdResource::class;
}
