<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users;

use App\Enums\AdminPermission;
use App\Enums\TrustScoreTier;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\AdsRelationManager;
use App\Filament\Admin\Resources\Users\RelationManagers\PaymentsRelationManager;
use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use App\Models\TrustScore;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Membres';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'firstname';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['city', 'agency', 'trustScores']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Photo de profil')
                    ->icon(Heroicon::Camera)
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Avatar')
                            ->disk(config('filesystems.app_media_disk'))
                            ->visibility('public')
                            ->directory('avatars')
                            ->avatar()
                            ->image()
                            ->imagePreviewHeight('160')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->fetchFileInformation(false)
                            ->downloadable(false)
                            ->openable(false)
                            ->previewable(true)
                            ->afterStateHydrated(function (FileUpload $component, ?string $state): void {
                                if (!is_string($state) || $state === '') {
                                    $component->state(null);

                                    return;
                                }
                                if (str_starts_with($state, 'http://') || str_starts_with($state, 'https://')) {
                                    $component->state(null);

                                    return;
                                }
                                $disk = config('filesystems.app_media_disk');
                                if (!Storage::disk($disk)->exists($state)) {
                                    $component->state(null);
                                }
                            })
                            ->uploadingMessage('Envoi en cours...')
                            ->helperText('Formats acceptés : JPEG, PNG, WebP (max 2 Mo)'),
                    ]),

                Section::make('Informations personnelles')
                    ->icon(Heroicon::User)
                    ->columns(2)
                    ->schema([
                        TextInput::make('firstname')
                            ->label('Prénom')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::User),
                        TextInput::make('lastname')
                            ->label('Nom')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::User),
                        Select::make('type')
                            ->label('Type')
                            ->options(UserType::class)
                            ->native(false)
                            ->nullable()
                            ->helperText('Type de compte utilisateur'),
                        Select::make('role')
                            ->label('Rôle')
                            ->options(UserRole::class)
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText('Définit les permissions de l\'utilisateur'),
                    ]),

                Section::make('Permissions administrateur')
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('warning')
                    ->description('Détermine les fonctionnalités du panel admin accessibles à cet utilisateur. Le statut « super-admin » donne tous les accès.')
                    ->visible(fn (Get $get): bool => $get('role') === UserRole::ADMIN->value || $get('role') === UserRole::ADMIN)
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_super_admin')
                            ->label('Super-administrateur')
                            ->helperText('Accès total à toutes les ressources sans restriction.')
                            ->live()
                            ->onColor('success')
                            ->columnSpanFull(),
                        Select::make('admin_permissions')
                            ->label('Permissions ciblées')
                            ->helperText('Sélectionnez précisément les fonctionnalités auxquelles cet administrateur peut accéder. Ignoré si le statut « super-admin » est activé.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => (bool) $get('is_super_admin'))
                            ->options(self::buildAdminPermissionOptions())
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->icon(Heroicon::Phone)
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Adresse email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Envelope),
                        TextInput::make('phone_number')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20)
                            ->prefixIcon(Heroicon::Phone),
                        Placeholder::make('email_verified_at')
                            ->label('Email vérifié le')
                            ->content(fn ($record) => $record?->email_verified_at?->format('d/m/Y H:i') ?? 'Non vérifié'),
                        Select::make('city_id')
                            ->label('Ville')
                            ->relationship('city', 'name')
                            ->nullable()
                            ->searchable()
                            ->placeholder('Choisir une ville')
                            ->searchDebounce(250)
                            ->preload()
                            ->suffixIcon(Heroicon::HomeModern)
                            ->loadingMessage('Chargement des villes...')
                            ->noSearchResultsMessage('Aucun résultat trouvé')
                            ->native(false),
                    ]),

                Section::make('Sécurité')
                    ->icon(Heroicon::LockClosed)
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Compte actif')
                            ->helperText('Désactivez pour bloquer l’accès de cet utilisateur à la plateforme.')
                            ->default(true)
                            ->onColor('success')
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->confirmed()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText('Minimum 8 caractères'),
                        TextInput::make('password_confirmation')
                            ->label('Confirmer le mot de passe')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->visible(fn (string $context): bool => $context === 'create'),
                    ]),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Profil ───────────────────────────────────────────────────────────
                Section::make('Profil')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('primary')
                    ->description('Identité et coordonnées de l\'utilisateur')
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->circular()
                            ->size(80)
                            ->disk(config('filesystems.app_media_disk'))
                            ->defaultImageUrl(fn ($record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->firstname.' '.$record->lastname)
                                .'&background=F6475F&color=fff&bold=true'
                            )
                            ->columnSpanFull(),
                        TextEntry::make('full_name')
                            ->label('Nom complet')
                            ->formatStateUsing(fn ($record): string => $record->firstname.' '.$record->lastname)
                            ->weight('bold')
                            ->size('lg')
                            ->icon(Heroicon::User)
                            ->columnSpanFull(),
                        TextEntry::make('email')
                            ->label('Email')
                            ->icon(Heroicon::Envelope)
                            ->iconColor('info')
                            ->copyable()
                            ->copyMessage('Email copié !'),
                        TextEntry::make('phone_number')
                            ->label('Téléphone')
                            ->icon(Heroicon::Phone)
                            ->iconColor('success')
                            ->copyable()
                            ->copyMessage('Numéro copié !')
                            ->placeholder('Non renseigné'),
                        TextEntry::make('city.name')
                            ->label('Ville')
                            ->icon(Heroicon::MapPin)
                            ->iconColor('warning')
                            ->placeholder('Non renseignée'),
                    ]),

                // ── Statut du compte ───────────────────────────────────────────
                Section::make('Compte')
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('success')
                    ->description('Permissions, statut et historique de connexion')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type de compte')
                            ->badge()
                            ->color(fn ($state): string => match ($state instanceof BackedEnum ? $state->value : (string) $state) {
                                'agency' => 'primary',
                                'individual' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('role')
                            ->label('Rôle')
                            ->badge()
                            ->color(fn ($state): string => match ($state instanceof BackedEnum ? $state->value : (string) $state) {
                                'admin' => 'danger',
                                'agent' => 'warning',
                                default => 'gray',
                            }),
                        IconEntry::make('is_active')
                            ->label('Compte actif')
                            ->boolean()
                            ->trueIcon(Heroicon::CheckCircle)
                            ->falseIcon(Heroicon::XCircle)
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('email_verified_at')
                            ->label('Email vérifié le')
                            ->icon(Heroicon::CheckBadge)
                            ->iconColor('success')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('Non vérifié'),
                        TextEntry::make('last_login_at')
                            ->label('Dernière connexion')
                            ->icon(Heroicon::Clock)
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('Jamais connecté')
                            ->since(),
                        TextEntry::make('created_at')
                            ->label('Membre depuis')
                            ->icon(Heroicon::CalendarDays)
                            ->since(),
                    ]),

                // ── Score de confiance ─────────────────────────────────────────
                Section::make('Score de confiance')
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('warning')
                    ->description('Scoring bidirectionnel (locataire / bailleur)')
                    ->columns(2)
                    ->visible(fn (User $record): bool => $record->trustScores->isNotEmpty())
                    ->schema([
                        TextEntry::make('trust_tenant')
                            ->label('Score Locataire')
                            ->getStateUsing(function (User $record): string {
                                $ts = $record->trustScores->firstWhere('role_context', 'tenant');

                                return $ts ? $ts->tier->label().' ('.$ts->score.'%)' : '—';
                            })
                            ->badge()
                            ->color(function (User $record): string {
                                $ts = $record->trustScores->firstWhere('role_context', 'tenant');

                                return $ts?->tier->color() ?? 'gray';
                            }),
                        TextEntry::make('trust_landlord')
                            ->label('Score Bailleur')
                            ->getStateUsing(function (User $record): string {
                                $ts = $record->trustScores->firstWhere('role_context', 'landlord');

                                return $ts ? $ts->tier->label().' ('.$ts->score.'%)' : '—';
                            })
                            ->badge()
                            ->color(function (User $record): string {
                                $ts = $record->trustScores->firstWhere('role_context', 'landlord');

                                return $ts?->tier->color() ?? 'gray';
                            }),
                        TextEntry::make('trust_computed_at')
                            ->label('Calculé le')
                            ->getStateUsing(function (User $record): ?string {
                                $ts = $record->trustScores->first();

                                return $ts?->computed_at?->format('d/m/Y à H:i');
                            })
                            ->icon(Heroicon::Clock)
                            ->placeholder('Jamais calculé'),
                    ]),

                // ── Agence ──────────────────────────────────────────────────────
                Section::make('Agence rattachée')
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('primary')
                    ->columns(2)
                    ->visible(fn (User $record): bool => $record->agency !== null)
                    ->schema([
                        TextEntry::make('agency.name')
                            ->label("Nom de l'agence")
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('primary')
                            ->weight('semibold'),
                        TextEntry::make('agency.slug')
                            ->label('Slug')
                            ->badge()
                            ->color('gray')
                            ->copyable()
                            ->copyMessage('Slug copié !'),
                    ]),

                // ── Permissions admin ───────────────────────────────────────────
                Section::make('Permissions administrateur')
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('warning')
                    ->visible(fn (User $record): bool => $record->isAdmin())
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_super_admin')
                            ->label('Super-administrateur')
                            ->boolean()
                            ->trueIcon(Heroicon::ShieldCheck)
                            ->falseIcon(Heroicon::ShieldExclamation)
                            ->trueColor('success')
                            ->falseColor('gray'),
                        TextEntry::make('admin_permissions')
                            ->label('Permissions accordées')
                            ->columnSpanFull()
                            ->badge()
                            ->separator(',')
                            ->color('warning')
                            ->getStateUsing(function (User $record): array {
                                if ($record->isSuperAdmin()) {
                                    return ['Accès total (super-admin)'];
                                }

                                $values = (array) ($record->admin_permissions ?? []);
                                if ($values === []) {
                                    return [];
                                }

                                return collect($values)
                                    ->map(fn (string $value): ?string => AdminPermission::tryFrom($value)?->getLabel())
                                    ->filter()
                                    ->values()
                                    ->all();
                            })
                            ->placeholder('Aucune permission accordée'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Utilisateurs')
            ->description('Liste des utilisateurs')
            ->deferLoading()
            ->striped()
            ->extremePaginationLinks()
            ->recordTitleAttribute('firstname')
            ->columns(static::getTableColumns())
            ->filters(static::getTableFilters())
            ->recordActions(static::getTableRecordActions())
            ->headerActions(static::getTableHeaderActions())
            ->toolbarActions(static::getTableToolbarActions());
    }

    public static function getTableColumns(): array
    {
        return [
            ImageColumn::make('avatar')
                ->label('Avatar')
                ->circular()
                ->size(40)
                ->disk(config('filesystems.app_media_disk'))
                ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->firstname.' '.$record->lastname).'&background=F6475F&color=fff'),
            TextColumn::make('full_name')
                ->label('Nom complet')
                ->formatStateUsing(fn ($record) => $record->firstname.' '.$record->lastname)
                ->searchable(['firstname', 'lastname']),
            TextColumn::make('phone_number')
                ->label('Téléphone')
                ->searchable()
                ->copyable()
                ->copyMessage('Numéro copié !')
                ->copyMessageDuration(1500),
            TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->copyable()
                ->copyMessage('Email copié !')
                ->copyMessageDuration(1500),
            TextColumn::make('email_verified_at')
                ->label('Email vérifié le')
                ->dateTime('d/m/Y à H:i')
                ->sortable(),
            TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('role')
                ->label('Rôle')
                ->badge()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('is_active')
                ->label('Actif')
                ->boolean()
                ->sortable(),
            TextColumn::make('trustScoreDisplay')
                ->label('TrustScore')
                ->badge()
                ->getStateUsing(function ($record): ?string {
                    /** @var User $record */
                    $isAgent = $record->role->value === 'agent' || $record->type?->value === 'agency';
                    $context = $isAgent ? 'landlord' : 'tenant';
                    $trustScore = $record->trustScores->firstWhere('role_context', $context)
                        ?? $record->trustScores->sortByDesc('score')->first();
                    if (!$trustScore) {
                        return null;
                    }

                    return $trustScore->tier->label().' ('.$trustScore->score.')';
                })
                ->color(function ($record): string {
                    /** @var User $record */
                    $isAgent = $record->role->value === 'agent' || $record->type?->value === 'agency';
                    $context = $isAgent ? 'landlord' : 'tenant';
                    $trustScore = $record->trustScores->firstWhere('role_context', $context)
                        ?? $record->trustScores->sortByDesc('score')->first();

                    return $trustScore?->tier->color() ?? 'gray';
                })
                ->sortable(query: fn ($query, string $direction) => $query->orderBy(
                    TrustScore::select('score')
                        ->whereColumn('trust_scores.user_id', 'users.id')
                        ->orderByDesc('score')
                        ->limit(1),
                    $direction
                ))
                ->toggleable(),
            TextColumn::make('city.name')
                ->label('Ville')
                ->searchable(),
            TextColumn::make('created_at')
                ->label('Créé le')
                ->dateTime('d/m/Y à H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),
            TextColumn::make('updated_at')
                ->label('Modifié le')
                ->dateTime('d/m/Y à H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),
            TextColumn::make('deleted_at')
                ->label('Supprimé le')
                ->dateTime('d/m/Y à H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),
        ];
    }

    public static function getTableFilters(): array
    {
        return [
            TrashedFilter::make(),
            Filter::make('is_active')
                ->label('utilisateurs actifs')
                ->toggle()
                ->query(fn (Builder $query) => $query->where('is_active', true)),
            SelectFilter::make('role')
                ->label('Filtrer par rôle')
                ->options([
                    'customer' => 'Clients',
                    'agent' => 'Agents',
                    'admin' => 'Admins',
                ])
                ->native(false),
            SelectFilter::make('type')
                ->label('Filtrer par type')
                ->options([
                    'individual' => 'Indépendant',
                    'agency' => 'Agence',
                ])
                ->native(false),
            SelectFilter::make('city_id')
                ->label('Ville')
                ->relationship('city', 'name')
                ->searchable()
                ->preload()
                ->native(false),
            Filter::make('created_at')
                ->label('Date d\'inscription')
                ->form([
                    DatePicker::make('created_from')
                        ->label('Du')
                        ->native(false),
                    DatePicker::make('created_until')
                        ->label('Au')
                        ->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['created_from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['created_from'] ?? null) {
                        $indicators[] = 'Inscrit depuis le '.Carbon::parse($data['created_from'])->format('d/m/Y');
                    }
                    if ($data['created_until'] ?? null) {
                        $indicators[] = 'Inscrit avant le '.Carbon::parse($data['created_until'])->format('d/m/Y');
                    }

                    return $indicators;
                }),
            Filter::make('email_verified')
                ->label('Email vérifié')
                ->toggle()
                ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
            Filter::make('has_ads')
                ->label('Avec annonces')
                ->toggle()
                ->query(fn (Builder $query) => $query->has('ads')),
            SelectFilter::make('trust_score_tier')
                ->label('TrustScore')
                ->options(
                    collect(TrustScoreTier::cases())
                        ->mapWithKeys(fn ($tier) => [$tier->value => $tier->label()])
                        ->all()
                )
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->whereHas('trustScores', fn ($q) => $q->where('tier', $data['value']))
                    : $query
                ),
        ];
    }

    public static function getTableRecordActions(): array
    {
        return [
            ViewAction::make()
                ->iconButton()
                ->slideOver()
                ->modalIcon('heroicon-o-user-circle')
                ->modalIconColor('primary')
                ->modalWidth('2xl'),
            EditAction::make()
                ->iconButton()
                ->successNotificationTitle('Utilisateur mis à jour'),
            DeleteAction::make()
                ->iconButton()
                ->successNotificationTitle('Utilisateur supprimé'),
            ForceDeleteAction::make()
                ->successNotificationTitle('Utilisateur supprimé définitivement'),
            RestoreAction::make()
                ->successNotificationTitle('Utilisateur restauré'),
        ];
    }

    public static function getTableHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Importer')
                ->importer(UserImporter::class)
                ->icon(Heroicon::ArrowUpTray),
            ExportAction::make()
                ->label('Exporter')
                ->exporter(UserExporter::class)
                ->icon(Heroicon::ArrowDownTray)
                ->formats([
                    ExportFormat::Csv,
                    ExportFormat::Xlsx,
                ]),
        ];
    }

    public static function getTableToolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ExportBulkAction::make()
                    ->label('Exporter')
                    ->exporter(UserExporter::class),
            ]),
        ];
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            AdsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Build the grouped option list for the `admin_permissions` multi-select.
     *
     * Returned format mirrors Filament's grouped Select API: `[group => [value => label]]`.
     *
     * @return array<string, array<string, string>>
     */
    protected static function buildAdminPermissionOptions(): array
    {
        $options = [];

        foreach (AdminPermission::grouped() as $group => $permissions) {
            foreach ($permissions as $permission) {
                $options[$group][$permission->value] = $permission->getLabel();
            }
        }

        return $options;
    }
}
