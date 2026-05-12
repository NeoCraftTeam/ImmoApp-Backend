<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Identifiant')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Identifiant copié'),
                ToggleColumn::make('is_active')
                    ->label('Actif'),
                TextColumn::make('updated_at')
                    ->label('Modifié')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
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
