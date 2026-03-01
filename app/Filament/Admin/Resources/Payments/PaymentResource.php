<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payments;

use App\Filament\Admin\Resources\Payments\Pages\ManagePayments;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Imports\PaymentImporter;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Administration';

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
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('type')
                    ->label('Type')
                    ->badge(),
                \Filament\Infolists\Components\TextEntry::make('amount')
                    ->label('Montant')
                    ->money('XAF'),
                \Filament\Infolists\Components\TextEntry::make('transaction_id')
                    ->label('ID Transaction')
                    ->copyable(),
                \Filament\Infolists\Components\TextEntry::make('payment_method')
                    ->label('Moyen de paiement')
                    ->badge(),
                \Filament\Infolists\Components\TextEntry::make('ad.title')
                    ->label('Annonce'),
                \Filament\Infolists\Components\TextEntry::make('user.fullname')
                    ->label('Utilisateur'),
                \Filament\Infolists\Components\TextEntry::make('status')
                    ->label('Statut')
                    ->badge(),
                \Filament\Infolists\Components\TextEntry::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i'),
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
            ])
            ->recordActions([
                ViewAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre de transactions';
    }
}
