<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Payments\Pages;

use App\Filament\Agency\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
