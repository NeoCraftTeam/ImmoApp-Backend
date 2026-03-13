<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ads\Concerns;

use App\Enums\AdStatus;
use App\Jobs\ProcessTourSceneJob;
use App\Models\Ad;
use App\Models\PropertyAttribute;
use App\Services\AiDescriptionEnhancer;
use Clickbar\Magellan\Data\Geometries\Point;
use Dotswan\MapPicker\Fields\Map;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
            // ── Section 1: Informations principales ──────────────
            Section::make('Informations principales')
                ->icon('heroicon-o-home-modern')
                ->description('Titre, description et catégorisation de votre bien')
                ->schema([
                    TextInput::make('title')
                        ->label('Titre de l\'annonce')
                        ->placeholder('Ex: Appartement 3 pièces vue mer — Bonanjo')
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Décrivez votre bien en détail : état, environnement, commodités à proximité…')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull()
                        ->hintAction(
                            Action::make('enhance_with_ai')
                                ->label('Améliorer avec l\'IA')
                                ->icon(Heroicon::Sparkles)
                                ->color('info')
                                ->tooltip('Utilisez l\'IA pour améliorer votre description en français professionnel')
                                ->action(function ($state, $set): void {
                                    if (empty(trim((string) $state))) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Description vide')
                                            ->body('Veuillez d\'abord saisir une description avant de l\'améliorer avec l\'IA.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $enhanced = app(AiDescriptionEnhancer::class)->enhance((string) $state);
                                    $set('description', $enhanced);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Description améliorée ✨')
                                        ->success()
                                        ->send();
                                })
                        ),
                ])
                ->columns(2)
                ->columnSpanFull(),

            // ── Section 2: Photos du bien ────────────────────────
            Section::make('Photos du bien')
                ->icon('heroicon-o-camera')
                ->description('Ajoutez jusqu\'à 10 photos (JPEG, PNG, WebP — max 5 Mo chacune). Glissez pour réordonner.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('images')
                        ->label('')
                        ->collection('images')
                        ->disk(config('filesystems.default'))
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->optimize('webp', 85)
                        ->resize(50)
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->imagePreviewHeight('150')
                        ->panelLayout('grid')
                        ->columnSpanFull()
                        ->getUploadedFileUsing(function ($component, string $file, $storedFileNames): ?array {
                            /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media|null $media */
                            $media = $component->getRecord()?->getRelationValue('media')->firstWhere('uuid', $file);

                            if (!$media) {
                                return null;
                            }

                            return [
                                'name' => $media->file_name,
                                'size' => 0,
                                'type' => $media->mime_type,
                                'url' => route('media.proxy', ['uuid' => $media->uuid]),
                            ];
                        })
                        ->extraAttributes([
                            'data-native-image' => 'true',
                            'data-native-image-camera' => 'true',
                        ]),
                ])
                ->collapsed(false)
                ->columnSpanFull(),

            // ── Section 2b: Quartier & Catégorie ──────────────────
            Section::make('Quartier & Catégorie')
                ->icon('heroicon-o-map')
                ->description('Localisation et type de bien')
                ->schema([
                    Select::make('quarter_id')
                        ->label('Quartier')
                        ->relationship('quarter', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('type_id')
                        ->label('Catégorie d\'annonce')
                        ->relationship('ad_type', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            // ── Section 3: Caractéristiques du bien ──────────────
            Section::make('Caractéristiques')
                ->icon('heroicon-o-squares-2x2')
                ->description('Surface, pièces et tarification')
                ->schema([
                    TextInput::make('adresse')
                        ->label('Adresse')
                        ->placeholder('Ex: Rue de la Liberté, Bonanjo')
                        ->required()
                        ->columnSpanFull(),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('price')
                                ->label('Prix')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('FCFA')
                                ->extraInputAttributes(['inputmode' => 'numeric']),
                            TextInput::make('surface_area')
                                ->label('Surface (m²)')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix('m²')
                                ->extraInputAttributes(['inputmode' => 'numeric']),
                            TextInput::make('bedrooms')
                                ->label('Chambres')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->suffix('🛏️')
                                ->extraInputAttributes(['inputmode' => 'numeric']),
                            TextInput::make('bathrooms')
                                ->label('Salles de bain')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->suffix('🚿')
                                ->extraInputAttributes(['inputmode' => 'numeric']),
                        ]),
                    Toggle::make('has_parking')
                        ->label('Parking inclus')
                        ->onIcon('heroicon-o-check')
                        ->offIcon('heroicon-o-x-mark')
                        ->onColor('success'),
                ])
                ->columns(1)
                ->columnSpanFull(),

            // ── Section 4: Équipements ──────────────────────────
            Section::make('Équipements & Services')
                ->icon('heroicon-o-check-circle')
                ->description('Sélectionnez les équipements disponibles dans ce bien')
                ->schema([
                    Select::make('attributes')
                        ->label('Équipements')
                        ->options(PropertyAttribute::toGroupedSelectArray())
                        ->placeholder('Sélectionnez les équipements')
                        ->multiple()
                        ->searchable()
                        ->preload()
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
                        })
                        ->columnSpanFull(),
                ])
                ->collapsed(false)
                ->collapsible()
                ->columnSpanFull(),

            // ── Section 5: Informations Premium ──────────────────
            Section::make('Informations Supplémentaires')
                ->icon('heroicon-o-lock-closed')
                ->description('Ce sont les informations supplémentaires qui seront comporteent les detailles de votre bien')
                ->schema([
                    Fieldset::make('Conditions du bail')
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
                        ])
                        ->columns(2),
                    Fieldset::make('Charges détaillées')
                        ->schema([
                            Toggle::make('charges_forfaitaires')
                                ->label('Charges au forfait')
                                ->helperText('Activez si les charges sont un montant fixe mensuel (eau, électricité incluses)')
                                ->onIcon('heroicon-o-check')
                                ->offIcon('heroicon-o-x-mark')
                                ->onColor('success')
                                ->live()
                                ->columnSpanFull(),
                            TextInput::make('charges_montant_forfait')
                                ->label('Montant forfaitaire mensuel')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('FCFA')
                                ->placeholder('Ex: 25 000')
                                ->extraInputAttributes(['inputmode' => 'numeric'])
                                ->visible(fn (callable $get): bool => (bool) $get('charges_forfaitaires')),
                            TextInput::make('charges_eau')
                                ->label('Frais d\'eau (mensuel)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('FCFA')
                                ->placeholder('Ex: 10 000')
                                ->extraInputAttributes(['inputmode' => 'numeric'])
                                ->visible(fn (callable $get): bool => !(bool) $get('charges_forfaitaires')),
                            TextInput::make('charges_electricite')
                                ->label('Frais d\'électricité (mensuel)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('FCFA')
                                ->placeholder('Ex: 15 000')
                                ->extraInputAttributes(['inputmode' => 'numeric'])
                                ->visible(fn (callable $get): bool => !(bool) $get('charges_forfaitaires')),
                            Textarea::make('charges_autres')
                                ->label('Autres charges')
                                ->placeholder('Ex: Gardiennage: 5 000 FCFA/mois, Ordures: 2 000 FCFA/mois')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    SpatieMediaLibraryFileUpload::make('property_condition')
                        ->collection('property_condition')
                        ->label('État des lieux (PDF)')
                        ->disk(config('filesystems.default'))
                        ->fetchFileInformation(false)
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->helperText('Document PDF de l\'état des lieux')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(true)
                ->columnSpanFull(),

            // ── Section 7: Localisation ──────────────────────────
            Section::make('Localisation sur la carte')
                ->icon('heroicon-o-map-pin')
                ->description('Positionnez votre bien sur la carte ou activez la géolocalisation')
                ->schema([
                    static::getMapField(),
                ])
                ->collapsible()
                ->collapsed(true)
                ->columnSpanFull(),
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
            ->label('Statut')
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
     * Owner-facing status section using ToggleButtons with colors & icons.
     * Hidden on create (auto PENDING) and while PENDING (awaiting admin approval).
     * Shown once the admin has approved the ad, allowing the owner to manage availability.
     */
    protected static function getOwnerStatusSection(): Section
    {
        return Section::make('Statut de l\'annonce')
            ->icon('heroicon-o-signal')
            ->description('Mettez à jour le statut de votre bien en fonction de sa disponibilité')
            ->schema([
                ToggleButtons::make('status')
                    ->label('')
                    ->required()
                    ->default(AdStatus::PENDING->value)
                    ->options(function (?Ad $record): array {
                        if ($record === null) {
                            return [AdStatus::PENDING->value => AdStatus::PENDING->getLabel()];
                        }

                        $options = [
                            $record->status->value => $record->status->getLabel(),
                        ];

                        foreach ($record->status->allowedTransitions() as $status) {
                            $options[$status->value] = $status->getLabel();
                        }

                        return $options;
                    })
                    ->colors([
                        AdStatus::AVAILABLE->value => 'success',
                        AdStatus::RESERVED->value => 'warning',
                        AdStatus::RENT->value => 'info',
                        AdStatus::SOLD->value => 'gray',
                        AdStatus::PENDING->value => 'gray',
                        AdStatus::DECLINED->value => 'danger',
                    ])
                    ->icons([
                        AdStatus::AVAILABLE->value => 'heroicon-o-check-circle',
                        AdStatus::RESERVED->value => 'heroicon-o-clock',
                        AdStatus::RENT->value => 'heroicon-o-key',
                        AdStatus::SOLD->value => 'heroicon-o-lock-closed',
                        AdStatus::PENDING->value => 'heroicon-o-ellipsis-horizontal-circle',
                        AdStatus::DECLINED->value => 'heroicon-o-x-circle',
                    ])
                    ->inline()
                    ->columnSpanFull(),
            ])
            ->hidden(fn (?Ad $record) => $record === null || $record->status === AdStatus::PENDING)
            ->columnSpanFull();
    }

    /**
     * Quarter + Type selects — shared across all panels.
     *
     * @return array<int, Select>
     */
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    #[\Deprecated(message: 'Quartier & Catégorie are now inline in getSharedFormFields().')]
    protected static function getRelationSelects(): array
    {
        return [];
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
                            $labelsBySlug = PropertyAttribute::query()->pluck('name', 'slug')->all();

                            if (empty($state)) {
                                return 'Aucun équipement spécifié';
                            }

                            if (is_array($state)) {
                                return collect($state)
                                    ->map(fn ($attr) => $labelsBySlug[$attr] ?? $attr)
                                    ->join(', ');
                            }

                            return $labelsBySlug[$state] ?? $state;
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
            Section::make('Visite Virtuelle 3D')
                ->icon('heroicon-o-cube-transparent')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('has_3d_tour')
                        ->label('Présence d\'un tour 3D')
                        ->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non')
                        ->badge()
                        ->color(fn ($state) => $state ? 'success' : 'danger'),
                    \Filament\Infolists\Components\TextEntry::make('tour_link')
                        ->label('Tour 3D')
                        ->default('Ouvrir le tour 3D')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->iconColor('info')
                        ->color('info')
                        ->url(fn ($record) => config('app.frontend_url')."/ads/{$record->id}")
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => (bool) $record->has_3d_tour),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),
        ];

        if ($showMeta) {
            $sections[] = Section::make('Méta-données')
                ->schema([
                    TextEntry::make('status')->label('Statut'),
                    TextEntry::make('user.fullname')->label('Publié par'),
                    TextEntry::make('created_at')->label('Créé le')->dateTime(),
                    TextEntry::make('updated_at')->label('Modifié le')->dateTime(),
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
                ->label('Titre')
                ->searchable(),
            TextColumn::make('adresse')
                ->label('Adresse')
                ->searchable(),
            TextColumn::make('price')
                ->label('Prix')
                ->money('xaf')
                ->sortable(),
            TextColumn::make('surface_area')
                ->label('Surface (m²)')
                ->numeric()
                ->sortable(),
        ];

        if ($isAdmin) {
            $columns[] = TextColumn::make('bedrooms')->label('Chambres')->numeric()->sortable();
            $columns[] = TextColumn::make('bathrooms')->label('Salles de bain')->numeric()->sortable();
            $columns[] = IconColumn::make('has_parking')->label('Parking')->boolean();
            $columns[] = TextColumn::make('location')
                ->label('Localisation')
                ->formatStateUsing(fn (?Point $state) => $state ? $state->getLatitude().', '.$state->getLongitude() : '-');
        }

        $columns[] = TextColumn::make('status')
            ->label('Statut')
            ->searchable()
            ->badge()
            ->formatStateUsing(fn ($state) => $state instanceof AdStatus ? $state->getLabel() : (AdStatus::tryFrom($state)?->getLabel() ?? $state))
            ->color(fn ($state) => match ($state instanceof AdStatus ? $state : AdStatus::tryFrom($state)) {
                AdStatus::AVAILABLE => 'success',
                AdStatus::RESERVED => 'warning',
                AdStatus::RENT => 'info',
                AdStatus::SOLD => 'gray',
                AdStatus::DECLINED => 'danger',
                AdStatus::PENDING => 'secondary',
                default => 'secondary',
            });

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
            $columns[] = TextColumn::make('expires_at')->label('Expiration')->dateTime()->sortable();
            $columns[] = TextColumn::make('user.fullname')
                ->label('Publié par')
                ->searchable(['firstname', 'lastname']);
            $columns[] = TextColumn::make('quarter.name')->label('Quartier')->searchable();
            $columns[] = TextColumn::make('ad_type.name')->label('Catégorie')->sortable();
        }

        $columns[] = TextColumn::make('created_at')
            ->label('Créé le')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: !$isAdmin);

        if ($isAdmin) {
            $columns[] = TextColumn::make('updated_at')
                ->label('Modifié le')
                ->dateTime()->sortable()
                ->toggleable(isToggledHiddenByDefault: true);
            $columns[] = TextColumn::make('deleted_at')
                ->label('Supprimé le')
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

    /**
     * Virtual tour (3D) section — upload photos + hotspot editor.
     */
    protected static function getTourSection(): Section
    {
        return Section::make('Visite Virtuelle 3D')
            ->description('Offrez à vos locataires une immersion complète dans votre bien.')
            ->icon('heroicon-o-cube-transparent')
            ->headerActions([
                Action::make('preview_tour')
                    ->label('Voir le tour (aperçu client)')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Ad $record) => config('app.frontend_url')."/ads/{$record->id}")
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record?->has_3d_tour === true),
            ])
            ->collapsible()
            ->schema([
                Section::make('Avant de commencer — Comment prendre vos photos 360°')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->description('Guide complet pour prendre de belles photos 360° avec votre téléphone')
                    ->collapsible()
                    ->collapsed(true)
                    ->schema([
                        Placeholder::make('guide_360')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="space-y-4 text-sm">
                                    <!-- Android Guide -->
                                    <div class="bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                        <div class="flex items-center gap-2 font-bold text-green-800 dark:text-green-400 mb-3">
                                            <span class="text-2xl">🤖</span>
                                            <span>Android — Google Camera (Recommandé)</span>
                                        </div>
                                        <ol class="list-decimal list-inside space-y-2 text-green-700 dark:text-green-300 ml-2">
                                            <li>Téléchargez <strong>Google Camera</strong> depuis le Play Store (gratuit)</li>
                                            <li>Ouvrez l\'app → Appuyez sur <strong>Plus</strong> → Sélectionnez <strong>Photo Sphere</strong></li>
                                            <li>Placez-vous <strong>au centre exact de la pièce</strong></li>
                                            <li>Suivez les cercles blancs à l\'écran en tournant <strong>lentement</strong> à 360°</li>
                                            <li>Attendez le traitement automatique (10-30 secondes)</li>
                                            <li>La photo 360° apparaît dans votre Galerie avec une icône 🌐</li>
                                        </ol>
                                    </div>

                                    <!-- iPhone Guide -->
                                    <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                        <div class="flex items-center gap-2 font-bold text-blue-800 dark:text-blue-400 mb-3">
                                            <span class="text-2xl">🍎</span>
                                            <span>iPhone (iOS 14+)</span>
                                        </div>
                                        <ol class="list-decimal list-inside space-y-2 text-blue-700 dark:text-blue-300 ml-2">
                                            <li>Ouvrez l\'app <strong>Appareil Photo</strong> native</li>
                                            <li>Sélectionnez le mode <strong>Panorama</strong></li>
                                            <li>Faites un panorama <strong>complet à 360°</strong> en tournant sur vous-même</li>
                                            <li>Gardez la flèche <strong>bien centrée</strong> sur la ligne horizontale</li>
                                            <li>Tournez doucement et régulièrement (environ 15 secondes)</li>
                                            <li><em>Alternative :</em> Téléchargez <strong>Panorama 360</strong> sur l\'App Store pour de meilleurs résultats</li>
                                        </ol>
                                    </div>

                                    <!-- Samsung Guide -->
                                    <div class="bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                                        <div class="flex items-center gap-2 font-bold text-purple-800 dark:text-purple-400 mb-3">
                                            <span class="text-2xl">📱</span>
                                            <span>Samsung Galaxy</span>
                                        </div>
                                        <ol class="list-decimal list-inside space-y-2 text-purple-700 dark:text-purple-300 ml-2">
                                            <li>Ouvrez l\'app <strong>Appareil Photo</strong> Samsung</li>
                                            <li>Balayez vers <strong>Plus</strong> dans les modes de prise de vue</li>
                                            <li>Sélectionnez <strong>Hyperlapse</strong> ou <strong>Directeur</strong></li>
                                            <li><strong>Recommandé :</strong> Utilisez plutôt <strong>Google Camera</strong> (gratuit sur Play Store) pour de meilleurs résultats</li>
                                        </ol>
                                    </div>

                                    <!-- Tips -->
                                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="font-bold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                                            <span class="text-xl">✅</span>
                                            <span>Conseils pour des photos parfaites</span>
                                        </div>
                                        <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300 ml-2">
                                            <li>Prenez vos photos en <strong>pleine lumière</strong> — ouvrez rideaux et volets ☀️</li>
                                            <li>Placez-vous au <strong>centre exact</strong> de chaque pièce</li>
                                            <li><strong>Ne bougez pas</strong> pendant la prise (restez immobile)</li>
                                            <li>Faites <strong>une photo par pièce</strong> : salon, chambre, cuisine, salle de bain...</li>
                                            <li>Format accepté : <strong>JPG ou WEBP</strong>, maximum <strong>30 Mo</strong> par photo</li>
                                            <li>Nommez vos fichiers clairement : <code>salon.jpg</code>, <code>chambre.jpg</code>, etc.</li>
                                        </ul>
                                    </div>
                                </div>
                            ')),
                    ]),
                Section::make('Étape 1 — Gestion des pièces')
                    ->description('Uploadez vos photos 360° et donnez-leur un nom.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Hidden::make('has_3d_tour'),
                        Hidden::make('tour_published_at'),
                        Repeater::make('tour_scenes')
                            ->label('')
                            ->statePath('tour_config.scenes')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Nom de la pièce')
                                            ->required()
                                            ->placeholder('Ex: Salon, Chambre parentale...'),
                                        Hidden::make('type')
                                            ->default('equirectangular'),

                                        FileUpload::make('image_path')
                                            ->label('Photo 360°')
                                            ->image()
                                            ->disk(config('filesystems.default'))
                                            ->fetchFileInformation(false)
                                            ->directory(fn (?Ad $record) => $record ? "ads/{$record->id}/tours" : 'ads/temp/tours')
                                            ->required()
                                            ->maxSize(30720)
                                            ->imagePreviewHeight('120')
                                            ->columnSpanFull()
                                            ->hint('Format panoramique 2:1 recommandé')
                                            ->getUploadedFileUsing(function ($component, string $file, $storedFileNames): array {
                                                // Build a proxy URL so existing R2 uploads render a live preview.
                                                // Supports both new path (ads/{adId}/tours/...) and legacy (tours/{adId}/...).
                                                $parts = explode('/', $file);
                                                if (count($parts) >= 4 && $parts[0] === 'ads') {
                                                    $adId = $parts[1];
                                                    $path = implode('/', array_slice($parts, 3)); // strip 'ads/{adId}/tours/'
                                                    $url = route('tour.image.proxy', ['adId' => $adId, 'path' => $path]);
                                                } elseif (count($parts) >= 3 && $parts[0] === 'tours') {
                                                    // Legacy: tours/{adId}/{filename}
                                                    $adId = $parts[1];
                                                    $path = implode('/', array_slice($parts, 2));
                                                    $url = route('tour.image.proxy', ['adId' => $adId, 'path' => $path]);
                                                } else {
                                                    $url = Storage::disk()->url($file);
                                                }

                                                return ['name' => basename($file), 'size' => 0, 'type' => 'image/webp', 'url' => $url];
                                            })
                                            ->deleteUploadedFileUsing(fn () => null)
                                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, ?Ad $record): string {
                                                $directory = $record ? "ads/{$record->id}/tours" : 'ads/temp/tours';
                                                $filename = Str::ulid().'.webp';
                                                $path = "{$directory}/{$filename}";

                                                $webp = ImageManager::gd()
                                                    ->read($file->getRealPath())
                                                    ->toWebp(quality: 82)
                                                    ->toString();

                                                Storage::disk()->put($path, $webp);

                                                return $path;
                                            }),
                                        Hidden::make('id')
                                            ->default(fn () => 'scene_'.Str::random(8)),
                                        Hidden::make('image_url'),
                                        Hidden::make('hotspots')
                                            ->default([]),
                                        Hidden::make('initial_view')
                                            ->default(['pitch' => 0, 'yaw' => 0, 'hfov' => 110]),
                                        Hidden::make('processing')->default(false),
                                        Hidden::make('processing_failed')->default(false),
                                        Hidden::make('cube_map')->default(null),
                                        Hidden::make('tiles_base_url')->default(null),
                                        Hidden::make('fallback_base_url')->default(null),
                                        Hidden::make('tiles_max_level')->default(null),
                                        Hidden::make('cube_resolution')->default(null),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->addActionLabel('Ajouter une pièce')
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->grid(2)
                            ->minItems(1)
                            ->columnSpanFull()
                            ->afterStateHydrated(function ($component, $record): void {
                                if ($record && $record->tour_config && isset($record->tour_config['scenes'])) {
                                    $scenes = collect($record->tour_config['scenes'])->map(function ($scene) {
                                        if (isset($scene['image_path']) && is_string($scene['image_path'])) {
                                            $scene['image_path'] = [$scene['image_path']];
                                        }

                                        return $scene;
                                    })->toArray();
                                    $component->state($scenes);
                                }
                            })
                            ->dehydrateStateUsing(function ($state, $record) {
                                $state = array_values((array) $state);
                                $seenSceneIds = [];
                                foreach ($state as $index => $scene) {
                                    $candidateId = (string) ($scene['id'] ?? '');
                                    if ($candidateId === '' || in_array($candidateId, $seenSceneIds, true)) {
                                        $candidateId = 'scene_'.Str::lower(Str::random(10));
                                        $state[$index]['id'] = $candidateId;
                                    }
                                    $seenSceneIds[] = $candidateId;
                                }

                                // Index the persisted scenes by ID so hotspots saved via the
                                // hotspot editor (PATCH) are not overwritten by the stale
                                // Hidden-field state when the main form is re-saved.
                                $persistedTourConfig = $record?->id
                                    ? Ad::query()
                                        ->whereKey($record->id)
                                        ->first()?->tour_config
                                    : null;

                                $persistedScenes = collect(($persistedTourConfig['scenes'] ?? []))->keyBy('id');

                                return collect($state)->map(function ($scene) use ($persistedScenes, $record) {
                                    if (isset($scene['image_path']) && !empty($scene['image_path'])) {
                                        // FileUpload state is an array of file keys; unwrap to a single path.
                                        $first = is_array($scene['image_path'])
                                            ? (array_values($scene['image_path'])[0] ?? null)
                                            : $scene['image_path'];

                                        if (!$first) {
                                            // User cleared the widget without uploading a replacement —
                                            // preserve the previously persisted image data.
                                            $persisted = $persistedScenes->get($scene['id']) ?? [];
                                            $scene['image_path'] = $persisted['image_path'] ?? null;
                                            $scene['image_url'] = $persisted['image_url'] ?? null;
                                            $scene['type'] ??= 'equirectangular';
                                            $scene['hotspots'] = $persisted['hotspots'] ?? $scene['hotspots'] ?? [];
                                            $scene['initial_view'] ??= ['pitch' => 0, 'yaw' => 0, 'hfov' => 110];

                                            return $scene;
                                        }

                                        $path = $first;
                                        // Support both new path (ads/{adId}/tours/{filename})
                                        // and legacy path (tours/{adId}/{filename})
                                        $parts = explode('/', (string) $path);
                                        if (count($parts) >= 4 && $parts[0] === 'ads') {
                                            $adId = $parts[1];
                                            $filename = $parts[3];
                                        } elseif (count($parts) >= 3 && $parts[0] === 'tours') {
                                            $adId = $parts[1];
                                            $filename = $parts[2];
                                        } else {
                                            $adId = $record->id ?? '';
                                            $filename = basename((string) $path);
                                        }

                                        // When uploaded during ad creation ($record was null), the file
                                        // landed in ads/temp/tours/. Now that the record exists, move it to
                                        // the correct location and fix the adId reference.
                                        if ($adId === 'temp' && $record?->id) {
                                            $correctPath = "ads/{$record->id}/tours/{$filename}";
                                            if (!Storage::disk()->exists($correctPath)) {
                                                Storage::disk()->copy($path, $correctPath);
                                            }
                                            $adId = $record->id;
                                            $path = $correctPath;
                                        }

                                        $scene['image_url'] = route('tour.image.proxy', [
                                            'adId' => $adId,
                                            'path' => $filename,
                                        ]);
                                        $scene['image_path'] = $path;

                                        // Dispatch background conversion for cubemap / multires
                                        // unless processing has already been done or is in progress.
                                        $sceneType = $scene['type'] ?? 'equirectangular';
                                        $needsProcessing = in_array($sceneType, ['cubemap', 'multires'])
                                            && empty($scene['cube_map'])
                                            && empty($scene['tiles_base_url'])
                                            && !($scene['processing'] ?? false)
                                            && $record?->id;

                                        if ($needsProcessing) {
                                            $scene['processing'] = true;
                                            $scene['processing_failed'] = false;
                                            ProcessTourSceneJob::dispatch(
                                                $record->id,
                                                $scene['id'],
                                                $sceneType,
                                                $path,
                                            );
                                        }
                                    }
                                    $scene['type'] ??= 'equirectangular';
                                    $scene['hotspots'] = $persistedScenes->get($scene['id'])['hotspots']
                                        ?? $scene['hotspots']
                                        ?? [];
                                    $scene['initial_view'] ??= ['pitch' => 0, 'yaw' => 0, 'hfov' => 110];

                                    return $scene;
                                })->values()->toArray();
                            })
                            ->afterStateUpdated(function ($state, $set, $record): void {
                                if (empty($state)) {
                                    $set('has_3d_tour', false);
                                    $set('tour_published_at', null);

                                    return;
                                }
                                $set('has_3d_tour', true);
                                $set('tour_published_at', now());
                                $set('tour_config.default_scene', $state[0]['id'] ?? null);
                            }),
                    ]),

                Section::make('Étape 2 — Liens entre les pièces')
                    ->description('Utilisez l\'éditeur ci-dessous pour relier vos pièces entre elles.')
                    ->icon('heroicon-o-link')
                    ->visible(fn (callable $get, $record) => (bool) $get('has_3d_tour') || $record?->has_3d_tour === true)
                    ->schema([
                        ViewField::make('tour_hotspot_editor')
                            ->label('')
                            ->view('filament.components.tour-hotspot-editor'),
                    ]),
            ])
            ->columnSpanFull();
    }
}
