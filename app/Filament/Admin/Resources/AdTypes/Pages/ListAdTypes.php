<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdTypes\Pages;

use App\Filament\Admin\Resources\AdTypes\AdTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdTypes extends ListRecords
{
    protected static string $resource = AdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Créer un type d\'annonce')
                ->modalHeading('Créer un nouveau type d\'annonce')
                ->mutateDataUsing(function (array $data): array { // Lier l'ID de l'utilisateur
                    $data['user_id'] = auth()->id();

                    return $data;
                }),

        ];
    }
}
