<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeaseContracts;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\LeaseContracts\Pages\ManageLeaseContracts;
use App\Models\LeaseContract;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class LeaseContractResource extends Resource
{
    protected static ?string $model = LeaseContract::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::UsersView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::DocumentCheck;

    protected static string|null|\UnitEnum $navigationGroup = 'Contrats & Réservations';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Contrat de location';

    protected static ?string $pluralLabel = 'Contrats de location';

    protected static ?string $navigationLabel = 'Contrats';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'ad'])
            ->latest();
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
                Section::make('Contrat')
                    ->icon(Heroicon::DocumentCheck)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contract_number')
                            ->label('Numéro')
                            ->icon(Heroicon::Hashtag)
                            ->copyable(),
                        TextEntry::make('user.name')
                            ->label('Bailleur')
                            ->icon(Heroicon::User),
                        TextEntry::make('ad.title')
                            ->label('Annonce')
                            ->icon(Heroicon::Home),
                        TextEntry::make('unit_reference')
                            ->label('Référence unité'),
                        TextEntry::make('monthly_rent')
                            ->label('Loyer mensuel')
                            ->money('XAF')
                            ->icon(Heroicon::Banknotes),
                        TextEntry::make('deposit_amount')
                            ->label('Caution')
                            ->money('XAF'),
                        TextEntry::make('lease_start')
                            ->label('Début bail')
                            ->date('d/m/Y'),
                        TextEntry::make('lease_end')
                            ->label('Fin bail')
                            ->date('d/m/Y'),
                        TextEntry::make('lease_duration_months')
                            ->label('Durée (mois)'),
                        TextEntry::make('special_conditions')
                            ->label('Conditions spéciales')
                            ->columnSpanFull(),
                    ]),
                Section::make('Locataire')
                    ->icon(Heroicon::UserCircle)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tenant_name')
                            ->label('Nom'),
                        TextEntry::make('tenant_phone')
                            ->label('Téléphone')
                            ->copyable(),
                        TextEntry::make('tenant_email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('tenant_id_number')
                            ->label("N° pièce d'identité"),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Numéro')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('Bailleur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant_name')
                    ->label('Locataire')
                    ->searchable(),
                TextColumn::make('monthly_rent')
                    ->label('Loyer')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('lease_start')
                    ->label('Début bail')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('lease_end')
                    ->label('Fin bail')
                    ->date('d/m/Y'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => ManageLeaseContracts::route('/'),
        ];
    }
}
