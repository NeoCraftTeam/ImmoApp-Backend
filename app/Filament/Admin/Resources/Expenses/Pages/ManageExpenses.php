<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
