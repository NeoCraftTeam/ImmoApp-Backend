<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XAF')
                    ->sortable(),

                TextColumn::make('duration_days')
                    ->label('Durée')
                    ->suffix(' jours')
                    ->sortable(),

                TextColumn::make('boost_score')
                    ->label('Boost')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => "+{$state} pts")
                    ->sortable(),

                TextColumn::make('boost_duration_days')
                    ->label('Durée boost')
                    ->suffix(' jours')
                    ->sortable(),

                TextColumn::make('max_ads')
                    ->label('Max annonces')
                    ->formatStateUsing(fn ($state) => $state ?? 'Illimité')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('subscriptions_count')
                    ->label('Abonnements')
                    ->counts('subscriptions')
                    ->badge()
                    ->color('info'),

                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                ViewAction::make(),
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
