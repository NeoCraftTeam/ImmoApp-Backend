<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Refunds\Pages;

use App\Filament\Admin\Resources\Refunds\RefundResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRefunds extends ManageRecords
{
    protected static string $resource = RefundResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
