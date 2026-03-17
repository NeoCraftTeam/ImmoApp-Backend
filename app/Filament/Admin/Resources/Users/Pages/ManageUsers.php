<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Créer un utilisateur')
                ->successNotificationTitle('Utilisateur créé avec succès')
                ->using(fn (array $data, string $model): User => DB::transaction(fn () => $model::create($data))),
        ];
    }
}
