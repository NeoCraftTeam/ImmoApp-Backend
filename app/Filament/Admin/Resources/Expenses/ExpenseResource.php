<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::PaymentsView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 5;

    protected static ?string $label = 'Dépense';

    protected static ?string $pluralLabel = 'Dépenses';

    protected static ?string $navigationLabel = 'Dépenses';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'ad'])
            ->latest('expense_date');
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
                Section::make('Dépense')
                    ->icon(Heroicon::CurrencyDollar)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Bailleur')
                            ->icon(Heroicon::User),
                        TextEntry::make('ad.title')
                            ->label('Annonce')
                            ->icon(Heroicon::Home),
                        TextEntry::make('category')
                            ->label('Catégorie')
                            ->badge(),
                        TextEntry::make('amount')
                            ->label('Montant')
                            ->money('XAF')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('expense_date')
                            ->label('Date')
                            ->date('d/m/Y'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        TextEntry::make('receipt_path')
                            ->label('Reçu')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Bailleur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->limit(35),
                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
