<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointTransactions;

use App\Enums\PointTransactionType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.fullname')
                    ->label('Utilisateur')
                    ->searchable(['firstname', 'lastname'])
                    ->sortable()
                    ->url(fn ($record): ?string => $record->user
                        ? route('filament.admin.resources.users.edit', $record->user)
                        : null
                    ),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (PointTransactionType $state): string => match ($state) {
                        PointTransactionType::PURCHASE => 'success',
                        PointTransactionType::UNLOCK => 'warning',
                        PointTransactionType::BONUS => 'info',
                        PointTransactionType::REFUND => 'gray',
                    })
                    ->formatStateUsing(fn (PointTransactionType $state): string => match ($state) {
                        PointTransactionType::PURCHASE => 'Achat',
                        PointTransactionType::UNLOCK => 'Déblocage',
                        PointTransactionType::BONUS => 'Bonus',
                        PointTransactionType::REFUND => 'Remboursement',
                    }),

                TextColumn::make('points')
                    ->label('Points')
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state >= 0 ? "+{$state}" : (string) $state)
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn ($record): string => $record->description),

                TextColumn::make('ad.id')
                    ->label('Annonce')
                    ->placeholder('—')
                    ->limit(8)
                    ->url(fn ($record): ?string => $record->ad
                        ? route('filament.admin.resources.ads.edit', $record->ad)
                        : null
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        PointTransactionType::PURCHASE->value => 'Achat',
                        PointTransactionType::UNLOCK->value => 'Déblocage',
                        PointTransactionType::BONUS->value => 'Bonus',
                        PointTransactionType::REFUND->value => 'Remboursement',
                    ]),
            ])
            ->paginated([25, 50, 100]);
    }
}
