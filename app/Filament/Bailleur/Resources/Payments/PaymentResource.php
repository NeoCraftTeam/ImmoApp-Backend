<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Payments;

use App\Filament\Bailleur\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|null|UnitEnum $navigationGroup = 'Mon Compte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    protected static ?string $navigationLabel = 'Mes Paiements';

    protected static ?string $modelLabel = 'Paiement';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Date')
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('xaf')
                    ->label('Montant'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('transaction_id')
                    ->label('Réf'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
