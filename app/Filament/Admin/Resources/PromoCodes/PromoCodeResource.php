<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromoCodes;

use App\Filament\Admin\Resources\PromoCodes\Pages\ManagePromoCodes;
use App\Models\PromoCode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::Ticket;

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 8;

    protected static ?string $label = 'Code promo';

    protected static ?string $pluralLabel = 'Codes promo';

    protected static ?string $navigationLabel = 'Codes promo';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(50)
                ->alphaNum()
                ->default(fn (): string => strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8)))
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated()
                ->columnSpan(1),
            TextInput::make('description')
                ->label('Description')
                ->maxLength(255)
                ->columnSpan(1),
            Select::make('discount_type')
                ->label('Type de réduction')
                ->required()
                ->options([
                    'percentage' => 'Pourcentage (%)',
                    'fixed' => 'Montant fixe (XAF)',
                ])
                ->columnSpan(1),
            TextInput::make('discount_value')
                ->label('Valeur de réduction')
                ->required()
                ->numeric()
                ->minValue(0)
                ->columnSpan(1),
            Select::make('applicable_to')
                ->label('Applicable à')
                ->required()
                ->options([
                    'all' => 'Tout',
                    'subscription' => 'Abonnements',
                    'credit' => 'Crédits',
                    'ad_unlock' => 'Déblocage annonce',
                ])
                ->default('all')
                ->columnSpan(1),
            TextInput::make('max_uses')
                ->label('Nombre maximum d\'utilisations')
                ->numeric()
                ->minValue(1)
                ->placeholder('Illimité')
                ->columnSpan(1),
            DateTimePicker::make('expires_at')
                ->label('Expire le')
                ->native(false)
                ->columnSpan(1),
            Toggle::make('is_active')
                ->label('Actif')
                ->default(true)
                ->columnSpan(1),
        ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Codes promo')
            ->description('Gérez les codes promotionnels et leurs réductions')
            ->striped()
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Code copié !')
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('discount_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Pourcentage',
                        'fixed' => 'Montant fixe',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'info',
                        'fixed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('discount_value')
                    ->label('Valeur')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->discount_type === 'percentage'
                        ? "{$record->discount_value}%"
                        : number_format($record->discount_value, 0, ',', ' ').' XAF')
                    ->sortable(),
                TextColumn::make('applicable_to')
                    ->label('Applicable à')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => 'Tout',
                        'subscription' => 'Abonnements',
                        'credit' => 'Crédits',
                        'ad_unlock' => 'Déblocage annonce',
                        default => $state,
                    }),
                TextColumn::make('used_count')
                    ->label('Utilisations')
                    ->sortable()
                    ->formatStateUsing(fn (PromoCode $record): string => $record->max_uses !== null
                        ? "{$record->used_count} / {$record->max_uses}"
                        : (string) $record->used_count),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('Jamais')
                    ->color(fn (?string $state): string => $state !== null && now()->gt($state) ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs')
                    ->native(false),
                SelectFilter::make('discount_type')
                    ->label('Type de réduction')
                    ->options([
                        'percentage' => 'Pourcentage',
                        'fixed' => 'Montant fixe',
                    ]),
                SelectFilter::make('applicable_to')
                    ->label('Applicable à')
                    ->options([
                        'all' => 'Tout',
                        'subscription' => 'Abonnements',
                        'credit' => 'Crédits',
                        'ad_unlock' => 'Déblocage annonce',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->successNotificationTitle('Code promo mis à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Code promo supprimé'),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePromoCodes::route('/'),
        ];
    }
}
