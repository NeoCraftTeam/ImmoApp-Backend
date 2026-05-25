<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Disputes\Tables;

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DisputesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Litiges')
            ->description('Gestion des litiges entre utilisateurs (locataires / bailleurs).')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Ouvert')
                    ->since()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('initiator.fullname')
                    ->label('Initiateur')
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('respondent.fullname')
                    ->label('Défendeur')
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('amount_claimed')
                    ->label('Montant')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', ' ').' FCFA' : '—'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        DisputeStatus::OPEN => 'danger',
                        DisputeStatus::UNDER_REVIEW => 'warning',
                        DisputeStatus::MEDIATION => 'info',
                        DisputeStatus::RESOLVED_INITIATOR,
                        DisputeStatus::RESOLVED_RESPONDENT,
                        DisputeStatus::RESOLVED_AMICABLY => 'success',
                        DisputeStatus::REJECTED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('sla_deadline')
                    ->label('SLA')
                    ->dateTime('d/m H:i')
                    ->color(fn ($record) => $record?->sla_deadline?->isPast() && $record->status->isOpen() ? 'danger' : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(DisputeStatus::class),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(DisputeType::class),
            ])
            ->recordActions([
                EditAction::make()->label('Ouvrir'),
            ]);
    }
}
