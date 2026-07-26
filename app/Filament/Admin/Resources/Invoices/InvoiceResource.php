<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\Invoices\Pages\ManageInvoices;
use App\Models\Invoice;
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

final class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::PaymentsView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Facture';

    protected static ?string $pluralLabel = 'Factures';

    protected static ?string $navigationLabel = 'Factures';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['agency'])
            ->latest('issued_at');
    }

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
                Section::make('Facture')
                    ->icon(Heroicon::DocumentText)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Numéro')
                            ->icon(Heroicon::Hashtag)
                            ->copyable(),
                        TextEntry::make('agency.name')
                            ->label('Agence')
                            ->icon(Heroicon::BuildingOffice),
                        TextEntry::make('plan_name')
                            ->label('Plan'),
                        TextEntry::make('billing_period')
                            ->label('Période')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('amount')
                            ->label('Montant')
                            ->money('XAF')
                            ->size('lg')
                            ->weight('bold')
                            ->icon(Heroicon::Banknotes)
                            ->iconColor('success'),
                        TextEntry::make('currency')
                            ->label('Devise'),
                        TextEntry::make('issued_at')
                            ->label('Émise le')
                            ->dateTime('d/m/Y'),
                        TextEntry::make('period_start')
                            ->label('Début période')
                            ->date('d/m/Y'),
                        TextEntry::make('period_end')
                            ->label('Fin période')
                            ->date('d/m/Y'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Numéro')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('agency.name')
                    ->label('Agence')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan_name')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('billing_period')
                    ->label('Période')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Devise'),
                TextColumn::make('issued_at')
                    ->label('Émise le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->filters([
                SelectFilter::make('billing_period')
                    ->options([
                        'monthly' => 'Mensuel',
                        'yearly' => 'Annuel',
                    ])
                    ->label('Période'),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageInvoices::route('/'),
        ];
    }
}
