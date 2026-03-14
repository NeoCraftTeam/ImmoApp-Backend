<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\LeaseContracts\Pages;

use App\Filament\Bailleur\Resources\LeaseContracts\LeaseContractResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaseContracts extends ManageRecords
{
    protected static string $resource = LeaseContractResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
