<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Payments\Pages\ManagePayments;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Imports\PaymentImporter;
use App\Models\Payment;
use App\Services\Payment\RefundService;
use BackedEnum;
use Filament\Actions\Action as RecordAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Transactions (Finances)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ad.ad_type', 'user']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        // Form is used only for the refund action; view modal uses infolist()
        return $schema->components([]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Transaction ─────────────────────────────────────────────
                Section::make('Transaction')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('success')
                    ->description('Détails du paiement enregistré')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Montant')
                            ->money('XAF')
                            ->size('lg')
                            ->weight('bold')
                            ->icon(Heroicon::Banknotes)
                            ->iconColor('success'),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (Payment $record): string => match ($record->status) {
                                PaymentStatus::SUCCESS => 'success',
                                PaymentStatus::PENDING => 'warning',
                                PaymentStatus::FAILED => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('type')
                            ->label('Type de paiement')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('payment_method')
                            ->label('Moyen de paiement')
                            ->badge()
                            ->color('primary')
                            ->icon(Heroicon::CreditCard),
                        TextEntry::make('transaction_id')
                            ->label('Référence transaction')
                            ->copyable()
                            ->copyMessage('Référence copiée !')
                            ->icon(Heroicon::QrCode)
                            ->badge()
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),

                // ── Parties concernées ───────────────────────────────────────
                Section::make('Parties concernées')
                    ->icon(Heroicon::Users)
                    ->iconColor('info')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.fullname')
                            ->label('Utilisateur')
                            ->icon(Heroicon::UserCircle)
                            ->iconColor('primary')
                            ->placeholder('Non associé'),
                        TextEntry::make('ad.title')
                            ->label('Annonce concernée')
                            ->icon(Heroicon::Megaphone)
                            ->iconColor('warning')
                            ->limit(60)
                            ->placeholder('Non associée'),
                    ]),

                // ── Horodatage ───────────────────────────────────────────────
                Section::make('Horodatage')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->icon(Heroicon::CalendarDays)
                            ->dateTime('d/m/Y à H:i'),
                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->icon(Heroicon::PencilSquare)
                            ->dateTime('d/m/Y à H:i'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Transactions financières')
            ->description('Historique des paiements et transactions')
            ->striped()
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->label('ID Transaction')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('ID copié !'),
                TextColumn::make('payment_method')
                    ->label('Moyen de paiement')
                    ->badge()
                    ->searchable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('ad.ad_type.name')
                    ->label('Catégorie')
                    ->searchable(),
                TextColumn::make('user.fullname')
                    ->label('Utilisateur')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(PaymentStatus::class),
                SelectFilter::make('payment_method')
                    ->label('Moyen de paiement')
                    ->options(PaymentMethod::class),
                Filter::make('date_range')
                    ->label('Période')
                    ->form([
                        DatePicker::make('from')->label('Du')->native(false),
                        DatePicker::make('until')->label('Au')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $labels = [];
                        if (!empty($data['from'])) {
                            $labels[] = 'Du '.$data['from'];
                        }
                        if (!empty($data['until'])) {
                            $labels[] = 'Au '.$data['until'];
                        }

                        return $labels;
                    }),
                Filter::make('amount_range')
                    ->label('Montant')
                    ->form([
                        TextInput::make('min_amount')->label('Montant min (XAF)')->numeric(),
                        TextInput::make('max_amount')->label('Montant max (XAF)')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['min_amount'] ?? null, fn ($q) => $q->where('amount', '>=', $data['min_amount']))
                        ->when($data['max_amount'] ?? null, fn ($q) => $q->where('amount', '<=', $data['max_amount']))
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalIcon('heroicon-o-banknotes')
                    ->modalIconColor('success')
                    ->modalHeading(fn (Payment $record): string => number_format((float) $record->amount, 0, ',', '\u202f').'\u00a0XAF'
                        .' \u2014 '.($record->transaction_id ?? 'Transaction')
                    )
                    ->modalWidth('2xl'),
                RecordAction::make('refund')
                    ->label('Rembourser')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rembourser ce paiement')
                    ->modalDescription('Le remboursement sera envoyé au gateway de paiement et les effets du paiement seront annulés.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motif du remboursement')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                        TextInput::make('amount')
                            ->label('Montant (laisser vide pour remboursement intégral)')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('XAF'),
                        Textarea::make('admin_note')
                            ->label('Note interne')
                            ->maxLength(1000),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            /** @var RefundService $service */
                            $service = app(RefundService::class);
                            $service->processRefund($record, auth()->user(), $data);

                            Notification::make()
                                ->title('Remboursement traité')
                                ->body('Le remboursement a été effectué avec succès.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Échec du remboursement')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::SUCCESS),
            ])
            ->headerActions([
                ImportAction::make()->label('Importer')
                    ->importer(PaymentImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(PaymentExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                // Immutable records
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    #[\Override]
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
