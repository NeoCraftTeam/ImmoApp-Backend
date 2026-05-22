<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LoginHistories\Pages;

use App\Filament\Admin\Resources\LoginHistories\LoginHistoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLoginHistories extends ManageRecords
{
    protected static string $resource = LoginHistoryResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
