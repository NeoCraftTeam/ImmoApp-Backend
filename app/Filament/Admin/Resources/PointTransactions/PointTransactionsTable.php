<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointTransactions;

use App\Enums\PointTransactionType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Transactions de crédits')
            ->description('Historique des achats, déblocages et bonus')
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with(['user.agency', 'ad']))
            ->defaultGroup(
                Group::make('user_id')
                    ->label('Utilisateur')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record): string => $record->user
                        ? "{$record->user->fullname} — Solde : {$record->user->point_balance} crédits"
                        : 'Utilisateur inconnu'
                    )
            )
            ->groups([
                Group::make('user_id')
                    ->label('Par utilisateur')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record): string => $record->user
                        ? "{$record->user->fullname} — Solde : {$record->user->point_balance} crédits"
                        : 'Utilisateur inconnu'
                    ),
                Group::make('type')
                    ->label('Par type')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn ($record): string => match ($record->type) {
                        PointTransactionType::PURCHASE => 'Achats',
                        PointTransactionType::UNLOCK => 'Déblocages',
                        PointTransactionType::BONUS => 'Bonus',
                        PointTransactionType::REFUND => 'Remboursements',
                        default => 'Autre',
                    }),
            ])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

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
                    ->label('Crédits')
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state >= 0 ? "+{$state}" : (string) $state)
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record): string => $record->description),

                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->placeholder('—')
                    ->limit(40),
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
