<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Subscriptions\Pages;

use App\Filament\Bailleur\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
