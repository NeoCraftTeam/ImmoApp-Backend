<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointTransactions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Filament\Admin\Resources\PointTransactions\Pages\ListPointTransactions;
use App\Models\PointTransaction;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PointTransactionResource extends Resource
{
    protected static ?string $model = PointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static \UnitEnum|string|null $navigationGroup = 'Crédits';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions de Crédits';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Transaction de crédits')
                    ->icon(Heroicon::Sparkles)
                    ->iconColor('warning')
                    ->description('Mouvement enregistré sur le portefeuille de crédits')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->icon(fn (PointTransactionType $state): string => match ($state) {
                                PointTransactionType::PURCHASE => Heroicon::ShoppingCart,
                                PointTransactionType::UNLOCK => Heroicon::LockOpen,
                                PointTransactionType::BONUS => Heroicon::Gift,
                                PointTransactionType::REFUND => Heroicon::ArrowUturnLeft,
                            })
                            ->color(fn (PointTransactionType $state): string => match ($state) {
                                PointTransactionType::PURCHASE => 'success',
                                PointTransactionType::UNLOCK => 'warning',
                                PointTransactionType::BONUS => 'info',
                                PointTransactionType::REFUND => 'gray',
                            })
                            ->formatStateUsing(fn (PointTransactionType $state): string => match ($state) {
                                PointTransactionType::PURCHASE => 'Achat de crédits',
                                PointTransactionType::UNLOCK => 'Déblocage d\'annonce',
                                PointTransactionType::BONUS => 'Bonus offert',
                                PointTransactionType::REFUND => 'Remboursement',
                            }),
                        TextEntry::make('points')
                            ->label('Mouvement')
                            ->size('lg')
                            ->weight('bold')
                            ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                            ->formatStateUsing(fn (int $state): string => ($state >= 0 ? '+' : '').number_format($state, thousands_separator: ' ').' crédits'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make('Utilisateur')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('primary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.fullname')
                            ->label('Nom complet')
                            ->icon(Heroicon::UserCircle)
                            ->placeholder('Utilisateur supprimé'),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->icon(Heroicon::Envelope)
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('user.point_balance')
                            ->label('Solde actuel')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn ($state): string => $state !== null ? number_format((int) $state, thousands_separator: ' ').' crédits' : '—'),
                        TextEntry::make('user.agency.name')
                            ->label('Agence')
                            ->icon(Heroicon::BuildingOffice2)
                            ->placeholder('—'),
                    ]),

                Section::make('Annonce associée')
                    ->icon(Heroicon::Megaphone)
                    ->iconColor('warning')
                    ->columns(2)
                    ->visible(fn (PointTransaction $record): bool => $record->ad !== null)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Titre')
                            ->columnSpanFull(),
                        TextEntry::make('ad.slug')
                            ->label('Slug')
                            ->badge()
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('ad.status')
                            ->label('Statut annonce')
                            ->badge()
                            ->placeholder('—'),
                    ]),

                Section::make('Paiement lié')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('success')
                    ->columns(2)
                    ->visible(fn (PointTransaction $record): bool => $record->payment !== null)
                    ->schema([
                        TextEntry::make('payment.amount')
                            ->label('Montant')
                            ->money('XAF')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('payment.status')
                            ->label('Statut paiement')
                            ->badge()
                            ->color(fn (?PaymentStatus $state): string => match ($state) {
                                PaymentStatus::SUCCESS => 'success',
                                PaymentStatus::PENDING => 'warning',
                                PaymentStatus::FAILED => 'danger',
                                PaymentStatus::CANCELLED => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?PaymentStatus $state): string => match ($state) {
                                PaymentStatus::SUCCESS => 'Réussi',
                                PaymentStatus::PENDING => 'En attente',
                                PaymentStatus::FAILED => 'Échoué',
                                PaymentStatus::CANCELLED => 'Annulé',
                                default => '—',
                            }),
                        TextEntry::make('payment.payment_method')
                            ->label('Moyen de paiement')
                            ->badge()
                            ->formatStateUsing(fn (?PaymentMethod $state): ?string => match ($state) {
                                PaymentMethod::ORANGE_MONEY => 'Orange Money',
                                PaymentMethod::MOBILE_MONEY => 'Mobile Money',
                                PaymentMethod::CARD => 'Carte bancaire',
                                PaymentMethod::FLUTTERWAVE => 'Flutterwave',
                                default => null,
                            })
                            ->placeholder('—'),
                        TextEntry::make('payment.transaction_id')
                            ->label('Référence')
                            ->copyable()
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),
                        TextEntry::make('payment.phone_number')
                            ->label('Téléphone')
                            ->icon(Heroicon::Phone)
                            ->placeholder('—'),
                        TextEntry::make('payment.gateway')
                            ->label('Passerelle')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—')
                            ->placeholder('—'),
                    ]),

                Section::make('Horodatage')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon(Heroicon::CalendarDays),
                        TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon(Heroicon::PencilSquare),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PointTransactionsTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPointTransactions::route('/'),
        ];
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }
}
