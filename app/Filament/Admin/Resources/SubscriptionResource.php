<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use App\Models\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Ventes';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Abonnements';

    protected static ?string $modelLabel = 'Abonnement';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('agency_id')
                    ->relationship('agency', 'name')
                    ->required()
                    ->searchable(),
                Select::make('subscription_plan_id')
                    ->relationship('plan', 'name')
                    ->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'active' => 'Actif',
                        'expired' => 'Expiré',
                        'cancelled' => 'Annulé',
                    ])
                    ->required(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                TextInput::make('amount_paid')
                    ->numeric()
                    ->prefix('FCFA'),
                Select::make('billing_period')
                    ->options([
                        'monthly' => 'Mensuel',
                        'yearly' => 'Annuel',
                    ])
                    ->required(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agency.name')
                    ->label('Agence')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'primary',
                        'active' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('billing_period')
                    ->label('Période')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'yearly' ? 'Annuel' : 'Mensuel'),
                TextColumn::make('amount_paid')
                    ->label('Montant')
                    ->money('XOF', divideBy: 1, locale: 'fr_FR')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'active' => 'Actif',
                        'expired' => 'Expiré',
                        'cancelled' => 'Annulé',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
