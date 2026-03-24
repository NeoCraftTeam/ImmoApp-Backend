<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SiteVisits\Pages;

use App\Filament\Admin\Resources\SiteVisits\SiteVisitResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSiteVisits extends ManageRecords
{
    protected static string $resource = SiteVisitResource::class;

    protected static ?string $title = 'Visites et paramètres UTM';

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
