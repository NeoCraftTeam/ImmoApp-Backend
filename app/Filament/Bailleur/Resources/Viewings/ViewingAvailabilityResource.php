<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Viewings;

use App\Enums\ReservationStatus;
use App\Filament\Bailleur\Resources\Viewings\Pages\ManageViewingAvailabilities;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\Zap\Schedule;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
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
                Action::make('edit')
                    ->label('Modifier')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Modifier la disponibilité')
                    ->modalDescription('Ajustez vos plages horaires. Attention : les réservations actives existantes ne sont pas affectées.')
                    ->form([
                        TextInput::make('name')
                            ->label('Nom (référence interne)')
                            ->required()
                            ->maxLength(100),

                        Grid::make(2)->schema([
                            Select::make('slot_duration')
                                ->label('Durée d\'un créneau')
                                ->options([
                                    15 => '15 min',
                                    20 => '20 min',
                                    30 => '30 min',
                                    45 => '45 min',
                                    60 => '1 heure',
                                    90 => '1h30',
                                    120 => '2 heures',
                                ])
                                ->required(),

                            TextInput::make('buffer_minutes')
                                ->label('Tampon entre créneaux')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(60)
                                ->suffix('min'),
                        ]),

                        Grid::make(2)->schema([
                            DatePicker::make('starts_on')
                                ->label('Date de début')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y'),

                            DatePicker::make('ends_on')
                                ->label('Date de fin (optionnel)')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                        ]),

                        Repeater::make('periods')
                            ->label('Plages horaires')
                            ->schema([
                                Grid::make(2)->schema([
                                    TimePicker::make('starts_at')
                                        ->label('Heure de début')
                                        ->seconds(false)
                                        ->required(),

                                    TimePicker::make('ends_at')
                                        ->label('Heure de fin')
                                        ->seconds(false)
                                        ->required(),
                                ]),
                            ])
                            ->addActionLabel('+ Ajouter une plage')
                            ->minItems(1)
                            ->reorderable()
                            ->cloneable()
                            ->collapsible(),
                    ])
                    ->fillForm(fn (Schedule $record): array => [
                        'name' => $record->name,
                        'slot_duration' => $record->metadata['slot_duration'] ?? 30,
                        'buffer_minutes' => $record->metadata['buffer_minutes'] ?? 0,
                        'starts_on' => $record->start_date->toDateString(),
                        'ends_on' => $record->end_date?->toDateString(),
                        'periods' => $record->periods->map(fn ($p): array => [
                            'starts_at' => \Carbon\Carbon::parse($p->start_time)->format('H:i'),
                            'ends_at' => \Carbon\Carbon::parse($p->end_time)->format('H:i'),
                        ])->toArray(),
                    ])
                    ->action(function (Schedule $record, array $data, \Livewire\Component $livewire): void {
                        try {
                            app(ReservationServiceInterface::class)->assertNoActiveReservationsForSchedule($record);
                        } catch (\App\Exceptions\Viewing\ScheduleHasActiveReservationsException) {
                            Notification::make()
                                ->title('Modification impossible')
                                ->body('Cette disponibilité a des réservations actives. Annulez-les d\'abord.')
                                ->danger()
                                ->send();

                            return;
                        }

                        /** @var Ad $ad */
                        $ad = $record->schedulable;

                        app(ViewingScheduleServiceInterface::class)->updateAvailability($ad, $record, [
                            'name' => $data['name'],
                            'slot_duration' => (int) $data['slot_duration'],
                            'buffer_minutes' => (int) ($data['buffer_minutes'] ?? 0),
                            'starts_on' => $data['starts_on'],
                            'ends_on' => $data['ends_on'] ?? null,
                            'periods' => $data['periods'] ?? [],
                        ]);

                        Notification::make()
                            ->title('Disponibilité mise à jour ✓')
                            ->success()
                            ->send();

                        // The old Schedule record was deleted and recreated with a new ID.
                        // Redirect so Filament does not try to reload the now-deleted record.
                        $livewire->redirect(ManageViewingAvailabilities::getUrl(), navigate: true);
                    }),

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
