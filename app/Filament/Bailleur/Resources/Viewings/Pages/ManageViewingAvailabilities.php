<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Viewings\Pages;

use App\Filament\Bailleur\Resources\Viewings\ViewingAvailabilityResource;
use App\Models\Ad;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;

class ManageViewingAvailabilities extends ManageRecords
{
    protected static string $resource = ViewingAvailabilityResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Nouvelle disponibilité')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading('Ajouter des créneaux de visite')
                ->modalDescription('Définissez vos plages horaires — les locataires pourront réserver directement.')
                ->form([
                    Select::make('ad_id')
                        ->label('Annonce concernée')
                        ->options(fn () => Ad::query()
                            ->where('user_id', auth()->id())
                            ->pluck('title', 'id')
                            ->toArray()
                        )
                        ->searchable()
                        ->required(),

                    TextInput::make('name')
                        ->label('Nom (référence interne)')
                        ->placeholder('Ex: Disponibilités semaine 12')
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
                            ->default(30)
                            ->required(),

                        TextInput::make('buffer_minutes')
                            ->label('Tampon entre créneaux')
                            ->numeric()
                            ->default(0)
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

                    Toggle::make('is_recurring')
                        ->label('Disponibilité récurrente')
                        ->live()
                        ->helperText('Activez si ces créneaux se répètent régulièrement.'),

                    Grid::make(2)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('is_recurring'))
                        ->schema([
                            Select::make('recurrence')
                                ->label('Fréquence')
                                ->options([
                                    'daily' => 'Tous les jours',
                                    'weekly' => 'Chaque semaine',
                                    'biweekly' => 'Toutes les 2 semaines',
                                    'monthly' => 'Chaque mois',
                                ])
                                ->live()
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('is_recurring')),
                        ]),

                    CheckboxList::make('recurrence_days')
                        ->label('Jours de la semaine')
                        ->helperText('Sélectionnez les jours où vous êtes disponible.')
                        ->options([
                            'monday' => 'Lundi',
                            'tuesday' => 'Mardi',
                            'wednesday' => 'Mercredi',
                            'thursday' => 'Jeudi',
                            'friday' => 'Vendredi',
                            'saturday' => 'Samedi',
                            'sunday' => 'Dimanche',
                        ])
                        ->columns(4)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('recurrence'), ['weekly', 'biweekly'])),

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
                ->action(function (array $data): void {
                    $ad = Ad::findOrFail($data['ad_id']);

                    $serviceData = [
                        'name' => $data['name'],
                        'starts_on' => $data['starts_on'],
                        'ends_on' => $data['ends_on'] ?? null,
                        'slot_duration' => (int) $data['slot_duration'],
                        'buffer_minutes' => (int) ($data['buffer_minutes'] ?? 0),
                        'periods' => $data['periods'] ?? [],
                        'recurrence' => $data['is_recurring'] ? ($data['recurrence'] ?? null) : 'once',
                        'recurrence_days' => $data['recurrence_days'] ?? null,
                        'days_of_month' => $data['days_of_month'] ?? null,
                    ];

                    app(ViewingScheduleServiceInterface::class)->createAvailability($ad, $serviceData);

                    Notification::make()
                        ->title('Disponibilité créée ✓')
                        ->body('Les créneaux sont maintenant réservables par les locataires.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
