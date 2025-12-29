<?php

namespace App\Filament\Bailleur\Resources\Ads;

use App\Enums\AdStatus;
use App\Filament\Bailleur\Resources\Ads\Pages\ManageAds;
use App\Models\Ad;
use BackedEnum;
use Clickbar\Magellan\Data\Geometries\Point;
use Dotswan\MapPicker\Fields\Map;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|null|UnitEnum $navigationGroup = 'Mes Biens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Mes Annonces';

    protected static ?string $modelLabel = 'Annonce';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
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
                DateTimePicker::make('expires_at'),
                Select::make('quarter_id')
                    ->relationship('quarter', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type_id')
                    ->relationship('ad_type', 'name')
                    ->required(),
            ]);
    }

    #[\Override]
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
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make('view'),
                EditAction::make('edit')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['location_map']) && is_array($data['location_map'])) {
                            $data['location'] = Point::make($data['location_map']['lat'], $data['location_map']['lng']);
                            unset($data['location_map']);
                        }

                        return $data;
                    }),
                DeleteAction::make('delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
        return (string) static::getModel()::where('user_id', auth()->id())->count();
    }
}
