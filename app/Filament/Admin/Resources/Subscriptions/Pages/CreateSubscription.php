<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    #[\Override]
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => static::getModel()::create($data));
    }
}
