<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Schemas;

use App\Models\Agency;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name')
                    ->label('Nom'),
                TextEntry::make('slug')
                    ->label('Slug'),
                TextEntry::make('logo')
                    ->label('Logo')
                    ->placeholder('-'),
                TextEntry::make('owner.fullname')
                    ->label('Propriétaire')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y à H:i')
                    ->visible(fn (Agency $record): bool => $record->trashed()),
            ]);
    }
}
