<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Filament\Admin\Resources\ActivityLogs\Pages\ManageActivityLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|null|UnitEnum $navigationGroup = 'Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Journal de Sécurité';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Activités';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Détails de l\'activité')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description'),
                        TextEntry::make('event')
                            ->label('Événement')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('causer.firstname')
                            ->label('Effectué par')
                            ->formatStateUsing(function ($record): string {
                                $causer = $record->causer;
                                if (!$causer) {
                                    return 'Système';
                                }

                                return "{$causer->firstname} {$causer->lastname}";
                            }),
                        TextEntry::make('subject_type')
                            ->label('Type d\'entité')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                        TextEntry::make('subject_id')
                            ->label('ID de l\'entité'),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y à H:i:s'),
                    ])->columns(3),

                Section::make('Modifications')
                    ->schema([
                        TextEntry::make('properties.old')
                            ->label('Anciennes valeurs')
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => '<pre style="font-size: 0.8em; overflow-x: auto;">'.json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</pre>')
                            ->html(),
                        TextEntry::make('properties.attributes')
                            ->label('Nouvelles valeurs')
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => '<pre style="font-size: 0.8em; overflow-x: auto;">'.json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</pre>')
                            ->html(),
                    ])
                    ->visible(fn ($record): bool => !empty($record->properties->get('old')) || !empty($record->properties->get('attributes')))
                    ->collapsed(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Entité')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        \App\Models\Ad::class => 'Annonce',
                        \App\Models\User::class => 'Utilisateur',
                        \App\Models\Agency::class => 'Agence',
                        \App\Models\Subscription::class => 'Abonnement',
                        \App\Models\SubscriptionPlan::class => 'Plan',
                        \App\Models\Payment::class => 'Paiement',
                        default => $state ? class_basename($state) : '-',
                    })
                    ->sortable(),
                TextColumn::make('causer.firstname')
                    ->label('Admin')
                    ->formatStateUsing(function ($record): string {
                        $causer = $record->causer;
                        if (!$causer) {
                            return 'Système';
                        }

                        return "{$causer->firstname} {$causer->lastname}";
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Entité')
                    ->options([
                        \App\Models\Ad::class => 'Annonce',
                        \App\Models\User::class => 'Utilisateur',
                        \App\Models\Agency::class => 'Agence',
                        \App\Models\Subscription::class => 'Abonnement',
                        \App\Models\SubscriptionPlan::class => 'Plan',
                        \App\Models\Payment::class => 'Paiement',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivityLogs::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Activités aujourd\'hui';
    }
}
