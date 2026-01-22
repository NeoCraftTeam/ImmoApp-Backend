<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\Ads\Pages\ManageAds;
use App\Filament\Exports\AdExporter;
use App\Filament\Imports\AdImporter;
use App\Models\Ad;
use BackedEnum;
use Clickbar\Magellan\Data\Geometries\Point;
use Dotswan\MapPicker\Fields\Map;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

    #[\Override]
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
                SpatieMediaLibraryFileUpload::make('images')
                    ->collection('images')
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(10)
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
                Map::make('location_map')
                    ->label('Localisation')
                    ->columnSpanFull()
                    ->defaultLocation(latitude: 4.0511, longitude: 9.7679)
                    ->afterStateHydrated(function ($state, $record, callable $set): void {
                        if ($record?->location) {
                            $set('location_map', [
                                'lat' => $record->location->getLatitude(),
                                'lng' => $record->location->getLongitude(),
                            ]);
                        }
                    })
                    ->showMarker()
                    ->draggable()
                    ->showMyLocationButton()
                    ->showZoomControl()
                    ->tilesUrl('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
                    ->zoom(15),
                Select::make('status')
                    ->options(AdStatus::class)
                    ->required()
                    ->default(AdStatus::AVAILABLE),
                Select::make('user_id')
                    ->relationship('user', 'firstname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->fullname)
                    ->searchable(['firstname', 'lastname'])
                    ->preload()
                    ->required(),
                Select::make('quarter_id')
                    ->relationship('quarter', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type_id')
                    ->relationship('ad_type', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Apperçu')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('images')
                            ->collection('images')
                            ->label('Galerie Photos')
                            ->columnSpanFull(),
                    ]),
                Section::make('Détails')
                    ->schema([
                        TextEntry::make('title')->label('Titre'),
                        TextEntry::make('price')->money('xaf')->label('Prix'),
                        TextEntry::make('adresse')->label('Adresse')->columnSpanFull(),
                        TextEntry::make('description')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Caractéristiques')
                    ->schema([
                        TextEntry::make('surface_area')->label('Surface')->suffix(' m²'),
                        TextEntry::make('bedrooms')->label('Chambres'),
                        TextEntry::make('bathrooms')->label('Salles de bain'),
                        IconEntry::make('has_parking')->label('Parking')->boolean(),
                    ])->columns(4),
                Section::make('Méta-données')
                    ->schema([
                        TextEntry::make('status'),
                        TextEntry::make('user.fullname')->label('Publié par'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])->columns(4)->collapsed(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                \Filament\Tables\Columns\SpatieMediaLibraryImageColumn::make('images')
                    ->collection('images')
                    ->conversion('thumb')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->label('Photos'),
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
                TextColumn::make('location')
                    ->formatStateUsing(fn (?Point $state) => $state ? $state->getLatitude().', '.$state->getLongitude() : '-'),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.fullname')
                    ->label('Publié par')
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('quarter.name')
                    ->searchable(),
                TextColumn::make('ad_type.name')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['location_map']) && is_array($data['location_map'])) {
                            $data['location'] = Point::make($data['location_map']['lat'], $data['location_map']['lng']);
                            unset($data['location_map']);
                        }

                        return $data;
                    }),
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
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The number of ads';
    }
}
