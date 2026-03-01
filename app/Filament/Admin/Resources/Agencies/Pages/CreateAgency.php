<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Pages;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    #[\Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Agence créée avec succès';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->url(AgencyResource::getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }

    #[\Override]
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => static::getModel()::create($data));
    }
}
