<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SearchAlerts\Pages;

use App\Filament\Admin\Resources\SearchAlerts\SearchAlertResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSearchAlerts extends ManageRecords
{
    protected static string $resource = SearchAlertResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
