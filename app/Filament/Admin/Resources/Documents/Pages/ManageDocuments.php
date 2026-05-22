<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Documents\Pages;

use App\Filament\Admin\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDocuments extends ManageRecords
{
    protected static string $resource = DocumentResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
