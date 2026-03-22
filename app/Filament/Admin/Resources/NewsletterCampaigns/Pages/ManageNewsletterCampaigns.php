<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsletterCampaigns\Pages;

use App\Filament\Admin\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageNewsletterCampaigns extends ManageRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvelle campagne')
                ->successNotificationTitle('Campagne créée'),
        ];
    }
}
