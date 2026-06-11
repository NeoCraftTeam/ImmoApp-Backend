<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SearchAlerts;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\SearchAlerts\Pages\ManageSearchAlerts;
use App\Models\SearchAlert;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SearchAlertResource extends Resource
{
    protected static ?string $model = SearchAlert::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::AdsView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::Bell;

    protected static string|null|\UnitEnum $navigationGroup = 'Annonces';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Alerte de recherche';

    protected static ?string $pluralLabel = 'Alertes de recherche';

    protected static ?string $navigationLabel = 'Alertes de recherche';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
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
                Section::make('Alerte')
                    ->icon(Heroicon::Bell)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Utilisateur')
                            ->icon(Heroicon::User),
                        TextEntry::make('label')
                            ->label('Libellé'),
                        TextEntry::make('city_name')
                            ->label('Ville'),
                        TextEntry::make('type_name')
                            ->label('Type de bien'),
                        TextEntry::make('price_min')
                            ->label('Prix min')
                            ->money('XAF'),
                        TextEntry::make('price_max')
                            ->label('Prix max')
                            ->money('XAF'),
                        TextEntry::make('bedrooms_min')
                            ->label('Chambres min'),
                        TextEntry::make('surface_min')
                            ->label('Surface min (m²)'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        IconEntry::make('notify_email')
                            ->label('Email')
                            ->boolean(),
                        IconEntry::make('notify_push')
                            ->label('Push')
                            ->boolean(),
                        TextEntry::make('last_notified_at')
                            ->label('Dernière notif.')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('query')
                            ->label('Requête NLP')
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
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Libellé')
                    ->searchable(),
                TextColumn::make('city_name')
                    ->label('Ville'),
                TextColumn::make('type_name')
                    ->label('Type'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('notify_email')
                    ->label('Email')
                    ->boolean(),
                IconColumn::make('notify_push')
                    ->label('Push')
                    ->boolean(),
                TextColumn::make('last_notified_at')
                    ->label('Dernière notif.')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('notify_email')
                    ->label('Email activé'),
                TernaryFilter::make('notify_push')
                    ->label('Push activé'),
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
            'index' => ManageSearchAlerts::route('/'),
        ];
    }
}
