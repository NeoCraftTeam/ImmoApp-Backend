<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads\Pages;

use App\Filament\Admin\Resources\Ads\AdResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAd extends CreateRecord
{
    protected static string $resource = AdResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Annonce créée avec succès';
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(AdResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AdResource::mutateLocationMapData($data);
    }

    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => static::getModel()::create($data));
    }
}
