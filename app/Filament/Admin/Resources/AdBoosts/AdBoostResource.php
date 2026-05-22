<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdBoosts;

use App\Enums\AdBoostStatus;
use App\Filament\Admin\Resources\AdBoosts\Pages\ManageAdBoosts;
use App\Models\AdBoost;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AdBoostResource extends Resource
{
    protected static ?string $model = AdBoost::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::RocketLaunch;

    protected static string|null|\UnitEnum $navigationGroup = 'Annonces';

    protected static ?int $navigationSort = 9;

    protected static ?string $label = 'Boost';

    protected static ?string $pluralLabel = 'Boosts d\'annonces';

    protected static ?string $navigationLabel = 'Boosts';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ad', 'user'])
            ->latest('started_at');
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Boost')
                    ->icon(Heroicon::RocketLaunch)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Annonce')
                            ->icon(Heroicon::Home),
                        TextEntry::make('user.name')
                            ->label('Bailleur')
                            ->icon(Heroicon::User),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (AdBoostStatus $state): string => match ($state) {
                                AdBoostStatus::Active => 'success',
                                AdBoostStatus::Expired => 'gray',
                                AdBoostStatus::Cancelled => 'danger',
                            }),
                        TextEntry::make('credits_spent')
                            ->label('Crédits dépensés')
                            ->numeric(),
                        TextEntry::make('boost_score')
                            ->label('Score boost')
                            ->numeric(),
                        TextEntry::make('duration_days')
                            ->label('Durée (jours)'),
                        TextEntry::make('started_at')
                            ->label('Démarré le')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('expires_at')
                            ->label('Expire le')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label('Bailleur')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (AdBoostStatus $state): string => match ($state) {
                        AdBoostStatus::Active => 'success',
                        AdBoostStatus::Expired => 'gray',
                        AdBoostStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('credits_spent')
                    ->label('Crédits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('boost_score')
                    ->label('Score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('Durée (j)'),
                TextColumn::make('started_at')
                    ->label('Démarré le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(AdBoostStatus::class)
                    ->label('Statut'),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageAdBoosts::route('/'),
        ];
    }
}
