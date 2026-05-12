<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports\Pages;

use App\Filament\Admin\Resources\AdReports\AdReportResource;
use Filament\Resources\Pages\ListRecords;

class ListAdReports extends ListRecords
{
    protected static string $resource = AdReportResource::class;
}
