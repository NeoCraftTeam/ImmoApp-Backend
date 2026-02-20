<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ads\Concerns;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\PropertyAttribute;
use Clickbar\Magellan\Data\Geometries\Point;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;

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
                ->appendFiles()
                ->maxFiles(10)
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('80')
                ->columnSpan(1),
            TextInput::make('adresse')
                ->required(),
            TextInput::make('price')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('FCFA'),
            TextInput::make('surface_area')
                ->required()
                ->numeric()
                ->minValue(1),
            TextInput::make('bedrooms')
                ->required()
                ->numeric()
                ->minValue(0),
            TextInput::make('bathrooms')
                ->required()
                ->numeric()
                ->minValue(0),
            Toggle::make('has_parking')
                ->label('Parking inclus'),
            Section::make('Équipements & Services')
                ->icon('heroicon-o-check-circle')
                ->description('Sélectionnez les équipements disponibles dans ce bien')
                ->schema([
                    CheckboxList::make('attributes')
                        ->label('')
                        ->options(PropertyAttribute::toSelectArray())
                        ->columns(3)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->searchable()
                        ->afterStateHydrated(function ($component, $state): void {
                            if (!is_array($state)) {
                                $component->state([]);

                                return;
                            }
                            $validSlugs = array_keys(PropertyAttribute::toSelectArray());
                            $component->state(array_values(array_intersect($state, $validSlugs)));
                        })
                        ->dehydrateStateUsing(function ($state) {
                            if (!is_array($state)) {
                                return [];
                            }
                            $validSlugs = array_keys(PropertyAttribute::toSelectArray());

                            return array_values(array_intersect($state, $validSlugs));
                        }),
                ])
                ->collapsed(false)
                ->columnSpanFull(),
            Section::make('Visibilité & Disponibilité')
                ->icon('heroicon-o-eye')
                ->description('Contrôlez quand votre annonce est visible')
                ->schema([
                    Toggle::make('is_visible')
                        ->label('Annonce visible')
                        ->helperText('Désactivez pour masquer temporairement votre annonce')
                        ->default(true),
                    DatePicker::make('available_from')
                        ->label('Disponible à partir de')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->helperText('Laissez vide pour "Immédiatement"'),
                    DatePicker::make('available_to')
                        ->label('Disponible jusqu\'au')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('available_from')
                        ->helperText('Laissez vide pour "Indéfiniment"'),
                ])
                ->columns(3)
                ->collapsed(false)
                ->columnSpanFull(),
            Section::make('Informations Premium')
                ->icon('heroicon-o-lock-closed')
                ->description('Ces informations seront visibles uniquement après paiement par les utilisateurs')
                ->schema([
                    Select::make('deposit_amount')
                        ->label('Dépôt de garantie')
                        ->options([
                            '1 mois' => '1 mois',
                            '2 mois' => '2 mois',
                            '3 mois' => '3 mois',
                            '4 mois' => '4 mois',
                            '5 mois' => '5 mois',
                        ])
                        ->placeholder('Sélectionnez le dépôt requis')
                        ->native(false),
                    Select::make('minimum_lease_duration')
                        ->label('Durée minimum du bail')
                        ->options([
                            '6 mois' => '6 mois',
                            '1 an renouvelable' => '1 an renouvelable',
                            '2 ans renouvelable' => '2 ans renouvelable',
                            '3 ans renouvelable' => '3 ans renouvelable',
                        ])
                        ->placeholder('Sélectionnez la durée minimale')
                        ->native(false),
                    Textarea::make('detailed_charges')
                        ->label('Charges détaillées')
                        ->placeholder('Ex: Eau/électricité: 15 000 FCFA/mois, Gardiennage: 5 000 FCFA/mois')
                        ->helperText('Détail des charges mensuelles')
                        ->rows(3)
                        ->columnSpanFull(),
                    SpatieMediaLibraryFileUpload::make('property_condition')
                        ->collection('property_condition')
                        ->label('État des lieux (PDF)')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->helperText('Document PDF de l\'état des lieux')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsed(true)
                ->columnSpanFull(),
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
     * On create, defaults to PENDING (hidden for non-admin).
     * On edit, shows current + allowed next states.
     * For non-admin: hidden when PENDING (awaiting admin approval), visible after approval.
     */
    protected static function getStatusSelect(bool $isAdmin = false): Select
    {
        $select = Select::make('status')
            ->required()
            ->default(AdStatus::PENDING->value)
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
                // Hidden on create (auto-set to PENDING) or while still PENDING (awaiting admin approval)
                // Using hidden() instead of visible() so the default value is still dehydrated
                ->hidden(fn (?Ad $record) => $record === null || $record->status === AdStatus::PENDING)
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
                ])
                ->columnSpanFull(),
            Section::make('Détails')
                ->schema([
                    TextEntry::make('title')->label('Titre'),
                    TextEntry::make('price')->money('xaf')->label('Prix'),
                    TextEntry::make('adresse')->label('Adresse')->columnSpanFull(),
                    TextEntry::make('description')->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Caractéristiques')
                ->schema([
                    TextEntry::make('surface_area')->label('Surface')->suffix(' m²'),
                    TextEntry::make('bedrooms')->label('Chambres'),
                    TextEntry::make('bathrooms')->label('Salles de bain'),
                    IconEntry::make('has_parking')->label('Parking')->boolean(),
                ])
                ->columns(4)
                ->columnSpanFull(),
            Section::make('Équipements & Services')
                ->schema([
                    TextEntry::make('attributes')
                        ->label('')
                        ->badge()
                        ->formatStateUsing(function ($state) {
                            if (empty($state)) {
                                return 'Aucun équipement spécifié';
                            }

                            if (is_array($state)) {
                                return collect($state)
                                    ->map(function ($attr) {
                                        $property = PropertyAttribute::query()->where('slug', $attr)->first();

                                        return $property ? $property->name : $attr;
                                    })
                                    ->join(', ');
                            }

                            $property = PropertyAttribute::query()->where('slug', $state)->first();

                            return $property ? $property->name : $state;
                        })
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),
            Section::make('Disponibilité')
                ->schema([
                    IconEntry::make('is_visible')->label('Visible')->boolean(),
                    TextEntry::make('available_from')
                        ->label('Disponible à partir de')
                        ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'Immédiatement'),
                    TextEntry::make('available_to')
                        ->label('Disponible jusqu\'au')
                        ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'Indéfiniment'),
                ])
                ->columns(3)
                ->columnSpanFull(),
            Section::make('Informations Premium')
                ->icon('heroicon-o-sparkles')
                ->description('Informations exclusives visibles après paiement par les locataires')
                ->schema([
                    TextEntry::make('deposit_amount')
                        ->label('Dépôt de garantie')
                        ->icon('heroicon-o-banknotes')
                        ->iconColor('warning')
                        ->placeholder('Non renseigné')
                        ->default('—'),
                    TextEntry::make('minimum_lease_duration')
                        ->label('Durée minimum du bail')
                        ->icon('heroicon-o-calendar-days')
                        ->iconColor('success')
                        ->placeholder('Non renseigné')
                        ->default('—'),
                    TextEntry::make('detailed_charges')
                        ->label('Charges mensuelles')
                        ->icon('heroicon-o-calculator')
                        ->iconColor('info')
                        ->placeholder('Non renseigné')
                        ->default('—'),
                    TextEntry::make('property_condition')
                        ->label('État des lieux')
                        ->icon('heroicon-o-document-text')
                        ->iconColor('primary')
                        ->formatStateUsing(fn ($record) => $record->hasMedia('property_condition') ? 'Télécharger le PDF' : 'Non fourni')
                        ->url(fn ($record) => $record->hasMedia('property_condition') ? $record->getFirstMediaUrl('property_condition') : null)
                        ->openUrlInNewTab()
                        ->badge()
                        ->color(fn ($record) => $record->hasMedia('property_condition') ? 'success' : 'gray'),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),
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

        // Views count for bailleurs/agencies
        if (!$isAdmin) {
            $columns[] = TextColumn::make('views_count')
                ->label('Vues')
                ->state(fn (Ad $record): int => $record->views()->count())
                ->icon('heroicon-o-eye')
                ->sortable(query: fn (Builder $query, string $direction) => $query
                    ->withCount('views')
                    ->orderBy('views_count', $direction));
        }

        // Visibility column for bailleurs/agencies to see hidden ads
        if (!$isAdmin) {
            $columns[] = IconColumn::make('is_visible')
                ->label('Visible')
                ->boolean()
                ->trueIcon('heroicon-o-eye')
                ->falseIcon('heroicon-o-eye-slash')
                ->trueColor('success')
                ->falseColor('danger');
        }

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
    public static function mutateLocationMapData(array $data): array
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
