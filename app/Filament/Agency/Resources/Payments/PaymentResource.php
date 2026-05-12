<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Agency\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Support\PaymentPresentation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|null|UnitEnum $navigationGroup = 'Gestion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static ?string $navigationLabel = 'Historique des paiements';

    protected static ?string $modelLabel = 'Paiement';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['ad']);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
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
                            ->label('Type')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('presentation_method')
                            ->label('Moyen réel')
                            ->state(fn (Payment $record): string => PaymentPresentation::forPayment($record)['payment_method_label']),
                        TextEntry::make('presentation_detail')
                            ->label('Complément trace')
                            ->state(fn (Payment $record): ?string => PaymentPresentation::forPayment($record)['payment_method_detail'])
                            ->placeholder('—'),
                        TextEntry::make('presentation_gateway_label')
                            ->label('Passerelle')
                            ->state(fn (Payment $record): string => PaymentPresentation::forPayment($record)['gateway_label'])
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('payment_method')
                            ->label('Code moyen')
                            ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? $state->value : (string) ($state ?? ''))
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('transaction_id')
                            ->label('Référence')
                            ->copyable()
                            ->copyMessage('Référence copiée !')
                            ->icon(Heroicon::QrCode)
                            ->badge()
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),

                Section::make('Annonce concernée')
                    ->icon(Heroicon::Megaphone)
                    ->iconColor('info')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Titre')
                            ->icon(Heroicon::HomeModern)
                            ->placeholder('Non associée'),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->icon(Heroicon::CalendarDays)
                            ->dateTime('d/m/Y à H:i'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Historique des paiements')
            ->description('Vos transactions des 12 derniers mois')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays),
                TextColumn::make('amount')
                    ->money('xaf')
                    ->label('Montant')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (Payment $record): string => match ($record->status) {
                        PaymentStatus::SUCCESS => 'success',
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_trace')
                    ->label('Moyen / trace')
                    ->state(function (Payment $record): string {
                        $row = PaymentPresentation::forPayment($record);
                        $line = $row['payment_method_label'];

                        if (($row['payment_method_detail'] ?? '') !== '') {
                            $line .= ' — '.$row['payment_method_detail'];
                        }

                        return $line;
                    })
                    ->description(fn (Payment $record): string => PaymentPresentation::forPayment($record)['gateway_label'])
                    ->wrap(),
                TextColumn::make('transaction_id')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Référence copiée !')
                    ->toggleable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->limit(30),
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
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
