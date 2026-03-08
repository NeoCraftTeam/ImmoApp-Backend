<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointTransactions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Transactions de crédits')
            ->description('Historique des achats, déblocages et bonus')
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with(['user.agency', 'ad', 'payment']))
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

                TextColumn::make('payment.payment_method')
                    ->label('Moyen de paiement')
                    ->badge()
                    ->color(fn (?PaymentMethod $state): string => match ($state) {
                        PaymentMethod::ORANGE_MONEY => 'warning',
                        PaymentMethod::MOBILE_MONEY => 'info',
                        PaymentMethod::STRIPE => 'primary',
                        PaymentMethod::FLUTTERWAVE => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?PaymentMethod $state): string => match ($state) {
                        PaymentMethod::ORANGE_MONEY => 'Orange Money',
                        PaymentMethod::MOBILE_MONEY => 'Mobile Money',
                        PaymentMethod::STRIPE => 'Carte bancaire',
                        PaymentMethod::FLUTTERWAVE => 'Flutterwave',
                        default => '—',
                    })
                    ->placeholder('—'),

                TextColumn::make('payment.amount')
                    ->label('Montant payé')
                    ->money('XAF')
                    ->placeholder('—'),

                TextColumn::make('payment.transaction_id')
                    ->label('Référence')
                    ->copyable()
                    ->limit(20)
                    ->tooltip(fn ($record): ?string => $record->payment?->transaction_id)
                    ->placeholder('—'),

                TextColumn::make('payment.phone_number')
                    ->label('Téléphone')
                    ->placeholder('—'),

                TextColumn::make('payment.status')
                    ->label('Statut paiement')
                    ->badge()
                    ->color(fn (?PaymentStatus $state): string => match ($state) {
                        PaymentStatus::SUCCESS => 'success',
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::CANCELLED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?PaymentStatus $state): string => match ($state) {
                        PaymentStatus::SUCCESS => 'Réussi',
                        PaymentStatus::PENDING => 'En attente',
                        PaymentStatus::FAILED => 'Échoué',
                        PaymentStatus::CANCELLED => 'Annulé',
                        default => '—',
                    })
                    ->placeholder('—'),
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

                SelectFilter::make('payment_method')
                    ->label('Moyen de paiement')
                    ->options([
                        PaymentMethod::ORANGE_MONEY->value => 'Orange Money',
                        PaymentMethod::MOBILE_MONEY->value => 'Mobile Money',
                        PaymentMethod::STRIPE->value => 'Carte bancaire',
                        PaymentMethod::FLUTTERWAVE->value => 'Flutterwave',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, string $value): Builder => $query->whereHas('payment', fn (Builder $q) => $q->where('payment_method', $value))
                    )),
            ])
            ->paginated([25, 50, 100]);
    }
}
