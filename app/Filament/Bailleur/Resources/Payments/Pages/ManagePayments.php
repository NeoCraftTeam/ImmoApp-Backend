<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Payments\Pages;

use App\Filament\Bailleur\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
