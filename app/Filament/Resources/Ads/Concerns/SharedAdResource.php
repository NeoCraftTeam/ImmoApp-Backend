<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ads\Concerns;

use App\Enums\AdStatus;
use App\Models\Ad;
use Clickbar\Magellan\Data\Geometries\Point;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * Shared form, infolist, and table definitions for AdResource across all panels.
 *
 * Usage: `use SharedAdResource;` in each panel's AdResource class.
 */
trait SharedAdResource
{
    // ──────────────────────────────────────────────
    //  FORM
    // ──────────────────────────────────────────────

    /**
     * Common form fields shared by all panels.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function getSharedFormFields(): array
    {
        return [
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
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->columnSpanFull(),
            TextInput::make('adresse')
                ->required(),
            TextInput::make('price')
                ->numeric()
                ->prefix('FCFA'),
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
            static::getMapField(),
        ];
    }

    /**
     * Map field — identical across all panels.
     */
    protected static function getMapField(): Map
    {
        return Map::make('location_map')
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
            ->zoom(15);
    }

    /**
     * Status select — shows only valid transitions from the current status.
     * On create, defaults to PENDING. On edit, shows current + allowed next states.
     */
    protected static function getStatusSelect(bool $isAdmin = false): Select
    {
        $select = Select::make('status')
            ->required()
            ->default(AdStatus::PENDING)
            ->options(function (?Ad $record): array {
                if ($record === null) {
                    // Creating: only PENDING is allowed
                    return [AdStatus::PENDING->value => AdStatus::PENDING->getLabel()];
                }

                // Editing: current status + allowed transitions
                $options = [
                    $record->status->value => $record->status->getLabel(),
                ];
                foreach ($record->status->allowedTransitions() as $status) {
                    $options[$status->value] = $status->getLabel();
                }

                return $options;
            });

        if (!$isAdmin) {
            $select
                ->disabled(fn (?Ad $record) => $record === null || $record->status === AdStatus::PENDING)
                ->dehydrated();
        }

        return $select;
    }

    /**
     * Quarter + Type selects — shared across all panels.
     *
     * @return array<int, Select>
     */
    protected static function getRelationSelects(): array
    {
        return [
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
        ];
    }

    // ──────────────────────────────────────────────
    //  INFOLIST
    // ──────────────────────────────────────────────

    /**
     * Common infolist sections.
     *
     * @return array<int, Section>
     */
    protected static function getSharedInfolistSchema(bool $showMeta = false): array
    {
        $sections = [
            Section::make('Aperçu')
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
        ];

        if ($showMeta) {
            $sections[] = Section::make('Méta-données')
                ->schema([
                    TextEntry::make('status'),
                    TextEntry::make('user.fullname')->label('Publié par'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])->columns(4)->collapsed();
        }

        return $sections;
    }

    // ──────────────────────────────────────────────
    //  TABLE
    // ──────────────────────────────────────────────

    /**
     * Common table columns.
     *
     * @return array<int, \Filament\Tables\Columns\Column>
     */
    protected static function getSharedTableColumns(bool $isAdmin = false): array
    {
        $columns = [
            \Filament\Tables\Columns\SpatieMediaLibraryImageColumn::make('images')
                ->collection('images')
                ->conversion('thumb')
                ->circular()
                ->stacked()
                ->limit(3)
                ->size(40)
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
        ];

        if ($isAdmin) {
            $columns[] = TextColumn::make('bedrooms')->numeric()->sortable();
            $columns[] = TextColumn::make('bathrooms')->numeric()->sortable();
            $columns[] = IconColumn::make('has_parking')->boolean();
            $columns[] = TextColumn::make('location')
                ->formatStateUsing(fn (?Point $state) => $state ? $state->getLatitude().', '.$state->getLongitude() : '-');
        }

        $columns[] = TextColumn::make('status')
            ->searchable()
            ->badge();

        if ($isAdmin) {
            $columns[] = TextColumn::make('expires_at')->dateTime()->sortable();
            $columns[] = TextColumn::make('user.fullname')
                ->label('Publié par')
                ->searchable(['firstname', 'lastname']);
            $columns[] = TextColumn::make('quarter.name')->searchable();
            $columns[] = TextColumn::make('ad_type.name')->sortable();
        }

        $columns[] = TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: !$isAdmin);

        if ($isAdmin) {
            $columns[] = TextColumn::make('updated_at')
                ->dateTime()->sortable()
                ->toggleable(isToggledHiddenByDefault: true);
            $columns[] = TextColumn::make('deleted_at')
                ->dateTime()->sortable()
                ->toggleable(isToggledHiddenByDefault: true);
        }

        return $columns;
    }

    /**
     * Mutation callback for map → Point conversion in EditAction.
     *
     * @return array<string, mixed>
     */
    protected static function mutateLocationMapData(array $data): array
    {
        if (isset($data['location_map']) && is_array($data['location_map'])) {
            $lat = $data['location_map']['lat'] ?? null;
            $lng = $data['location_map']['lng'] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                $data['location'] = Point::make((float) $lat, (float) $lng);
            }
            unset($data['location_map']);
        }

        return $data;
    }
}
