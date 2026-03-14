<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Bailleur\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Models\Subscription;
use BackedEnum;
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

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|null|UnitEnum $navigationGroup = 'Mon Compte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Mon Abonnement';

    protected static ?string $modelLabel = 'Abonnement';

    protected static ?string $pluralModelLabel = 'Abonnements';

    protected static ?int $navigationSort = 3;

    #[\Override]
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $agencyId = $user?->agency_id;

        return parent::getEloquentQuery()
            ->when($agencyId, fn (Builder $query) => $query->where('agency_id', $agencyId))
            ->when(!$agencyId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with(['plan', 'agency']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Détails de l\'abonnement')
                    ->icon('heroicon-o-credit-card')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plan.name')
                            ->label('Formule'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                            ->color(fn (SubscriptionStatus $state): string => $state->color()),

                        TextEntry::make('billing_period')
                            ->label('Période')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'monthly' => 'Mensuel',
                                'quarterly' => 'Trimestriel',
                                'yearly' => 'Annuel',
                                default => $state,
                            }),

                        TextEntry::make('amount_paid')
                            ->label('Montant payé')
                            ->money('XAF'),

                        TextEntry::make('starts_at')
                            ->label('Début')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),

                        TextEntry::make('ends_at')
                            ->label('Fin')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),

                        TextEntry::make('auto_renew')
                            ->label('Renouvellement auto')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non')
                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->iconColor(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ]),

                \Filament\Schemas\Components\Section::make('Informations complémentaires')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('agency.name')
                            ->label('Agence'),

                        TextEntry::make('cancelled_at')
                            ->label('Annulé le')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),

                        TextEntry::make('cancellation_reason')
                            ->label('Motif d\'annulation')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Mon abonnement')
            ->description('Gérez vos formules d\'abonnement et suivez leur état.')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('plan.name')
                    ->label('Formule')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color()),

                TextColumn::make('billing_period')
                    ->label('Période')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly' => 'Mensuel',
                        'quarterly' => 'Trimestriel',
                        'yearly' => 'Annuel',
                        default => $state,
                    }),

                TextColumn::make('amount_paid')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('Début')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->color(fn ($record): string => $record->ends_at?->isPast() ? 'danger' : 'success'),

                TextColumn::make('auto_renew')
                    ->label('Auto')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        SubscriptionStatus::PENDING->value => 'En attente',
                        SubscriptionStatus::ACTIVE->value => 'Actif',
                        SubscriptionStatus::EXPIRED->value => 'Expiré',
                        SubscriptionStatus::CANCELLED->value => 'Annulé',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Aucun abonnement')
            ->emptyStateDescription('Vous n\'avez pas encore souscrit à un abonnement.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $agencyId = $user?->agency_id;

        if (!$agencyId) {
            return null;
        }

        $activeCount = Subscription::query()
            ->where('agency_id', $agencyId)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->count();

        return $activeCount > 0 ? (string) $activeCount : null;
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Abonnements actifs';
    }

    #[\Override]
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}
