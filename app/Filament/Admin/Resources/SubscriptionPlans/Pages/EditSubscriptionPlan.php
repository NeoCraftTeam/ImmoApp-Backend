<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
