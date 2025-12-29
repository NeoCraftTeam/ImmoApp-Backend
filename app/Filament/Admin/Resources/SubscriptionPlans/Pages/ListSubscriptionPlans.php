<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
