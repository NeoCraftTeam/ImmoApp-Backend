<?php

namespace App\Filament\Admin\Resources\Ads;

use App\Filament\Admin\Resources\Ads\Pages\ManageAds;
use App\Filament\Exports\AdExporter;
use App\Filament\Imports\AdImporter;
use App\Models\Ad;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::InboxArrowDown;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Annonces';

    protected static ?string $modelLabel = 'Annonce';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->visible(false),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('adresse')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('surface_area')
                    ->required()
                    ->numeric(),
                TextInput::make('bedrooms')
                    ->required()
                    ->numeric(),
                TextInput::make('bathrooms')
                    ->required()
                    ->numeric(),
                Toggle::make('has_parking')
                    ->required(),
                TextInput::make('location'),
                TextInput::make('status')
                    ->required(),
                DateTimePicker::make('expires_at'),
                Select::make('user.fullname')
                    ->relationship('user', 'id')
                    ->required(),
                Select::make('quarter.name')
                    ->relationship('quarter', 'name')
                    ->required(),
                TextInput::make('type.name')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('slug')
                    ->visible(false),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('adresse'),
                TextEntry::make('price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('surface_area')
                    ->numeric(),
                TextEntry::make('bedrooms')
                    ->numeric(),
                TextEntry::make('bathrooms')
                    ->numeric(),
                IconEntry::make('has_parking')
                    ->boolean(),
                TextEntry::make('location')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('expires_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('user.fullname')
                    ->label('User'),
                TextEntry::make('quarter.name')
                    ->label('Quarter'),
                TextEntry::make('ad_type.name')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Ad $record): bool => $record->trashed()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('adresse')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('xaf')
                    ->sortable(),
                TextColumn::make('surface_area')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bedrooms')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bathrooms')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('has_parking')
                    ->boolean(),
                TextColumn::make('location'),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.fullname')
                    ->searchable(),
                TextColumn::make('quarter.name')
                    ->searchable(),
                TextColumn::make('ad_type.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->isoDate('LLLL', 'Europe/Paris')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->headerActions([
                ImportAction::make()->label('Importer')
                    ->importer(AdImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(AdExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAds::route('/'),
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
        return static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The number of ads';
    }
}
