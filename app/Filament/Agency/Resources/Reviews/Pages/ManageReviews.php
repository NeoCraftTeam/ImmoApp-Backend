<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Reviews\Pages;

use App\Filament\Agency\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
