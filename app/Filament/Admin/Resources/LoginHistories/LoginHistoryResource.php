<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LoginHistories;

use App\Filament\Admin\Resources\LoginHistories\Pages\ManageLoginHistories;
use App\Models\LoginHistory;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class LoginHistoryResource extends Resource
{
    protected static ?string $model = LoginHistory::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::LockClosed;

    protected static string|null|\UnitEnum $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Connexion';

    protected static ?string $pluralLabel = 'Historique de connexions';

    protected static ?string $navigationLabel = 'Connexions';

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
                Section::make('Connexion')
                    ->icon(Heroicon::LockClosed)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Utilisateur')
                            ->icon(Heroicon::User),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->icon(Heroicon::Envelope)
                            ->copyable(),
                        TextEntry::make('ip_address')
                            ->label('Adresse IP')
                            ->icon(Heroicon::GlobeAlt)
                            ->copyable(),
                        TextEntry::make('guard')
                            ->label('Guard')
                            ->badge(),
                        IconEntry::make('successful')
                            ->label('Succès')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),
                Section::make('Appareil')
                    ->icon(Heroicon::DevicePhoneMobile)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('device_type')
                            ->label('Type'),
                        TextEntry::make('browser')
                            ->label('Navigateur'),
                        TextEntry::make('platform')
                            ->label('Système'),
                        TextEntry::make('country')
                            ->label('Pays'),
                        TextEntry::make('city')
                            ->label('Ville'),
                        TextEntry::make('user_agent')
                            ->label('User-Agent')
                            ->columnSpanFull()
                            ->copyable(),
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
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('country')
                    ->label('Pays')
                    ->searchable(),
                TextColumn::make('browser')
                    ->label('Navigateur'),
                TextColumn::make('platform')
                    ->label('Système'),
                TextColumn::make('device_type')
                    ->label('Appareil')
                    ->badge(),
                TextColumn::make('guard')
                    ->label('Guard')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('successful')
                    ->label('Succès')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('successful')
                    ->label('Résultat'),
                SelectFilter::make('guard')
                    ->options([
                        'web' => 'Web',
                        'sanctum' => 'API',
                    ])
                    ->label('Guard'),
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
            'index' => ManageLoginHistories::route('/'),
        ];
    }
}
