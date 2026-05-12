<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromoCodes\Pages;

use App\Filament\Admin\Resources\PromoCodes\PromoCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePromoCodes extends ManageRecords
{
    protected static string $resource = PromoCodeResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successNotificationTitle('Code promo créé avec succès'),
        ];
    }
}
