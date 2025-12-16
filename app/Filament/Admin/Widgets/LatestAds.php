<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Ad;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAds extends BaseWidget
{
    protected static ?int $sort = 10;
    protected static ?string $heading = 'Dernières annonces en attente';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ad::query()->where('status', 'pending')->latest()
            )
            ->columns([
                Tables\Columns\ImageColumn::make('images.path')
                    ->label('Image')
                    ->limit(1)
                    ->circular(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.firstname')
                    ->label('Agent')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prix')
                    ->money('XAF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ville')
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Statut')
                    ->icon(fn (string $state): string => match ($state) {
                        'published' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('Approve')
                    ->label('Valider')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->action(fn (Ad $record) => $record->update(['status' => 'published'])),
            ]);
    }
}
