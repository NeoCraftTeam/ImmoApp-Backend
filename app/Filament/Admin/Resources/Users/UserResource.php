<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use App\Models\User;
use BackedEnum;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Utilisateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'firstname';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

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
                            ->disk('public')
                            ->directory('avatars')
                            ->avatar()
                            ->image()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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
                            ->helperText('Définit les permissions de l\'utilisateur'),
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
                ->disk('public')
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
        ];
    }

    public static function getTableRecordActions(): array
    {
        return [
            ViewAction::make()
                ->iconButton(),
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
    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
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

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre d\'utilisateurs';
    }
}
