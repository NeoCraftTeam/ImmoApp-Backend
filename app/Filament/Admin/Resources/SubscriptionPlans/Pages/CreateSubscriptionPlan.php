<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Plan d\'abonnement créé avec succès';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(SubscriptionPlanResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }
}
