<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributeCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PropertyAttributeCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Catégories des attributs')
            ->description('Regroupements des équipements affichés côté admin, owner et client')
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
                TextColumn::make('property_attributes_count')
                    ->label('Attributs')
                    ->counts('propertyAttributes')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Actif'),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->boolean()
                    ->trueLabel('Actives uniquement')
                    ->falseLabel('Inactives uniquement')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->successNotificationTitle('Catégorie mise à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Catégorie supprimée'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
