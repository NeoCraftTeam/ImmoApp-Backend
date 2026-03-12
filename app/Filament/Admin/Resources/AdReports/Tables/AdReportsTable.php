<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports\Tables;

use App\Enums\AdReportStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Signalements annonces')
            ->description('Suivi des annonces signalees par les clients')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),

                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable()
                    ->limit(40)
                    ->weight('bold'),

                TextColumn::make('reporter.fullname')
                    ->label('Signale par')
                    ->searchable(['firstname', 'lastname']),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        AdReportStatus::PENDING => 'danger',
                        AdReportStatus::REVIEWING => 'warning',
                        AdReportStatus::RESOLVED => 'success',
                        AdReportStatus::DISMISSED => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AdReportStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
