<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Refunds;

use App\Enums\RefundStatus;
use App\Filament\Admin\Resources\Refunds\Pages\ManageRefunds;
use App\Models\Refund;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
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

    protected static string|null|\UnitEnum $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Remboursement';

    protected static ?string $pluralLabel = 'Remboursements';

    protected static ?string $navigationLabel = 'Remboursements';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('payment.transaction_id')
                ->label('ID Transaction'),
            TextEntry::make('user.fullname')
                ->label('Utilisateur'),
            TextEntry::make('processedBy.fullname')
                ->label('Traité par'),
            TextEntry::make('amount')
                ->label('Montant')
                ->money('XAF'),
            TextEntry::make('status')
                ->label('Statut')
                ->badge(),
            TextEntry::make('reason')
                ->label('Motif')
                ->columnSpanFull(),
            TextEntry::make('admin_note')
                ->label('Note interne')
                ->columnSpanFull(),
            TextEntry::make('is_partial')
                ->label('Partiel')
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Partiel' : 'Total')
                ->color(fn (bool $state): string => $state ? 'warning' : 'info'),
            TextEntry::make('created_at')
                ->label('Créé le')
                ->dateTime('d/m/Y à H:i'),
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
                ViewAction::make(),
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
