<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Payments;

use App\Filament\Bailleur\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\Scopes\LandlordScope;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|null|UnitEnum $navigationGroup = 'Mon Compte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $navigationLabel = 'Mes Paiements';

    protected static ?string $modelLabel = 'Paiement';

    protected static ?string $pluralModelLabel = 'Paiements';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withGlobalScope('landlord', new LandlordScope)
            ->with(['ad.ad_type']);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Détails du paiement')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'success' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('amount')
                            ->label('Montant')
                            ->money('XAF')
                            ->icon('heroicon-o-currency-dollar')
                            ->iconColor('success'),
                        TextEntry::make('payment_method')
                            ->label('Moyen de paiement')
                            ->badge(),
                        TextEntry::make('transaction_id')
                            ->label('Référence')
                            ->copyable()
                            ->copyMessage('Référence copiée !')
                            ->icon('heroicon-o-clipboard-document'),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar'),
                    ]),
                Section::make('Annonce associée')
                    ->icon('heroicon-o-home')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Titre')
                            ->placeholder('—')
                            ->icon('heroicon-o-document-text'),
                        TextEntry::make('ad.ad_type.name')
                            ->label('Catégorie')
                            ->placeholder('—')
                            ->badge()
                            ->color('info'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Mes paiements')
            ->description('Historique de vos transactions financières')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y à H:i')
                    ->label('Date')
                    ->sortable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->limit(40)
                    ->placeholder('—')
                    ->searchable()
                    ->tooltip(fn ($record) => $record->ad?->title),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('XAF')
                    ->label('Montant')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Moyen')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('transaction_id')
                    ->label('Réf')
                    ->copyable()
                    ->copyMessage('Référence copiée !')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'unlock' => 'Déblocage',
                        'subscription' => 'Abonnement',
                        'boost' => 'Boost',
                        'credit' => 'Crédits',
                    ]),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'success' => 'Réussi',
                        'failed' => 'Échoué',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Aucun paiement')
            ->emptyStateDescription('Vos transactions apparaîtront ici.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre de paiements';
    }
}
