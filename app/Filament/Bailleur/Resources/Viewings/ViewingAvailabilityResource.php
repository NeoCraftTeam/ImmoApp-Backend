<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Viewings;

use App\Enums\ReservationStatus;
use App\Filament\Bailleur\Resources\Viewings\Pages\ManageViewingAvailabilities;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\Zap\Schedule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ViewingAvailabilityResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|null|UnitEnum $navigationGroup = 'Visites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Mes disponibilités';

    protected static ?string $modelLabel = 'Disponibilité';

    protected static ?string $pluralModelLabel = 'Disponibilités';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHasMorph('schedulable', Ad::class, fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->with(['schedulable', 'periods']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('schedulable.title')
                    ->label('Annonce')
                    ->limit(30)
                    ->icon('heroicon-o-home')
                    ->tooltip(fn (Schedule $record) => $record->schedulable instanceof Ad ? $record->schedulable->title : null),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'recurring' ? 'Récurrent' : 'Ponctuel')
                    ->color(fn (string $state) => $state === 'recurring' ? 'success' : 'info'),

                TextColumn::make('periods_count')
                    ->counts('periods')
                    ->label('Plages')
                    ->icon('heroicon-o-clock')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('active_reservations_count')
                    ->label('Réservations actives')
                    ->getStateUsing(fn (Schedule $record): int => TentativeReservation::query()
                        ->where('appointment_schedule_id', $record->id)
                        ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                        ->count()
                    )
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->icon('heroicon-o-calendar-days'),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'once' => 'Ponctuel',
                        'recurring' => 'Récurrent',
                    ]),

                SelectFilter::make('schedulable_id')
                    ->label('Annonce')
                    ->options(
                        fn () => Ad::query()
                            ->where('user_id', auth()->id())
                            ->pluck('title', 'id')
                            ->toArray()
                    )
                    ->searchable(),
            ])
            ->actions([
                DeleteAction::make()
                    ->successNotificationTitle('Disponibilité supprimée'),
            ])
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateHeading('Aucune disponibilité configurée')
            ->emptyStateDescription('Créez vos créneaux de visite pour que les locataires puissent réserver.');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageViewingAvailabilities::route('/'),
        ];
    }
}
