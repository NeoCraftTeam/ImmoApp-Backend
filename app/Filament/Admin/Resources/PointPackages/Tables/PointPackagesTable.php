<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PointPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                TextColumn::make('name')
                    ->label('Pack')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XAF')
                    ->sortable(),

                TextColumn::make('points_awarded')
                    ->label('Crédits')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => "{$state} crédits")
                    ->sortable(),

                TextColumn::make('badge')
                    ->label('Badge')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_popular')
                    ->label('Populaire')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
