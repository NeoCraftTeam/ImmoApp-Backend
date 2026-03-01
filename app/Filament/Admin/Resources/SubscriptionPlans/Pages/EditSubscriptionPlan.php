<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    #[\Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Plan d\'abonnement mis à jour';
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
            DeleteAction::make()->successNotificationTitle('Plan d\'abonnement supprimé'),
        ];
    }
}
