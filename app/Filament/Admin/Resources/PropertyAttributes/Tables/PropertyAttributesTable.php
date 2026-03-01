<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PropertyAttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Attributs de propriété')
            ->description('Caractéristiques disponibles pour les annonces')
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Identifiant')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Identifiant copié'),
                IconColumn::make('icon')
                    ->label('Icône')
                    ->icon(fn (string $state): string => $state),
                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Actif'),
                TextColumn::make('updated_at')
                    ->label('Modifié')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->boolean()
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->successNotificationTitle('Attribut mis à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Attribut supprimé'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
