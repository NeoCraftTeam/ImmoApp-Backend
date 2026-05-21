<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BoostPacksTable
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
                    ->description(fn ($record): string => $record->reach_description ?? ''),

                TextColumn::make('duration_days')
                    ->label('Durée')
                    ->suffix(' jours')
                    ->sortable(),

                TextColumn::make('boost_score')
                    ->label('Score boost')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => "+{$state} pts")
                    ->sortable(),

                TextColumn::make('price_credits')
                    ->label('Prix')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => "{$state} crédits")
                    ->sortable(),

                IconColumn::make('is_popular')
                    ->label('Recommandé')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')->label('Actif'),
                TernaryFilter::make('is_popular')->label('Recommandé'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
