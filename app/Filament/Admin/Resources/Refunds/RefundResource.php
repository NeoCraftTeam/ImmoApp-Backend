<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Refunds;

use App\Enums\RefundStatus;
use App\Filament\Admin\Resources\Refunds\Pages\ManageRefunds;
use App\Models\Refund;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ArrowUturnLeft;

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Remboursement';

    protected static ?string $pluralLabel = 'Remboursements';

    protected static ?string $navigationLabel = 'Remboursements';

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
                Section::make('Remboursement')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->iconColor('danger')
                    ->description('Détails du remboursement traité')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Montant remboursé')
                            ->money('XAF')
                            ->size('lg')
                            ->weight('bold')
                            ->icon(Heroicon::Banknotes)
                            ->iconColor('danger'),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (RefundStatus $state): string => match ($state) {
                                RefundStatus::Pending => 'warning',
                                RefundStatus::Processing => 'info',
                                RefundStatus::Completed => 'success',
                                RefundStatus::Failed => 'danger',
                            })
                            ->formatStateUsing(fn (RefundStatus $state): string => match ($state) {
                                RefundStatus::Pending => 'En attente',
                                RefundStatus::Processing => 'En cours',
                                RefundStatus::Completed => 'Complété',
                                RefundStatus::Failed => 'Échoué',
                            }),
                        TextEntry::make('is_partial')
                            ->label('Type de remboursement')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Partiel' : 'Total')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
                        TextEntry::make('payment.transaction_id')
                            ->label("Réf. paiement d'origine")
                            ->copyable()
                            ->copyMessage('Référence copiée !')
                            ->icon(Heroicon::QrCode)
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Parties concernées')
                    ->icon(Heroicon::Users)
                    ->iconColor('info')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.fullname')
                            ->label('Utilisateur remboursé')
                            ->icon(Heroicon::UserCircle)
                            ->iconColor('primary')
                            ->placeholder('Non renseigné'),
                        TextEntry::make('processedBy.fullname')
                            ->label('Traité par (admin)')
                            ->icon(Heroicon::ShieldCheck)
                            ->iconColor('danger')
                            ->placeholder('Non renseigné'),
                    ]),

                Section::make('Motif & Notes')
                    ->icon(Heroicon::DocumentText)
                    ->iconColor('warning')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Motif du remboursement')
                            ->prose()
                            ->columnSpanFull()
                            ->placeholder('Aucun motif renseigné'),
                        TextEntry::make('admin_note')
                            ->label('Note interne')
                            ->prose()
                            ->columnSpanFull()
                            ->placeholder('Aucune note interne'),
                    ]),

                Section::make('Horodatage')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Traité le')
                            ->icon(Heroicon::CalendarDays)
                            ->dateTime('d/m/Y à H:i'),
                        TextEntry::make('updated_at')
                            ->label('Mis à jour le')
                            ->icon(Heroicon::PencilSquare)
                            ->dateTime('d/m/Y à H:i'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Remboursements')
            ->description('Historique de tous les remboursements traités')
            ->striped()
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('payment.transaction_id')
                    ->label('ID Transaction')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('ID copié !'),
                TextColumn::make('user.fullname')
                    ->label('Utilisateur')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (RefundStatus $state): string => match ($state) {
                        RefundStatus::Pending => 'warning',
                        RefundStatus::Processing => 'info',
                        RefundStatus::Completed => 'success',
                        RefundStatus::Failed => 'danger',
                    })
                    ->formatStateUsing(fn (RefundStatus $state): string => match ($state) {
                        RefundStatus::Pending => 'En attente',
                        RefundStatus::Processing => 'En cours',
                        RefundStatus::Completed => 'Complété',
                        RefundStatus::Failed => 'Échoué',
                    })
                    ->sortable(),
                TextColumn::make('is_partial')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Partiel' : 'Total')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
                TextColumn::make('processedBy.fullname')
                    ->label('Traité par')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('reason')
                    ->label('Motif')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        RefundStatus::Pending->value => 'En attente',
                        RefundStatus::Processing->value => 'En cours',
                        RefundStatus::Completed->value => 'Complété',
                        RefundStatus::Failed->value => 'Échoué',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalIcon(fn (Refund $record): string => match ($record->status) {
                        RefundStatus::Completed => 'heroicon-o-check-circle',
                        RefundStatus::Failed => 'heroicon-o-x-circle',
                        RefundStatus::Processing => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-arrow-uturn-left',
                    })
                    ->modalIconColor(fn (Refund $record): string => match ($record->status) {
                        RefundStatus::Completed => 'success',
                        RefundStatus::Failed => 'danger',
                        RefundStatus::Processing => 'info',
                        default => 'warning',
                    })
                    ->modalHeading(fn (Refund $record): string => 'Remboursement — '.number_format((float) $record->amount, 0, ',', "\u{202F}")."\u{A0}XAF"),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageRefunds::route('/'),
        ];
    }
}
