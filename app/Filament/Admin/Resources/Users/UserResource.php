<?php

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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Utilisateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?string $recordTitleAttribute = 'firstname';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'Utilisateur';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->preserveFilenames()
                    ->avatar()
                    ->uploadingMessage('Uploading...')
                    ->image(),
                TextInput::make('firstname'),
                TextInput::make('lastname'),
                TextInput::make('phone_number')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->label('Mot de Passe')
                    ->password() // Transforme le champ en type 'password' (masque les caractères)
                    ->required() // Le mot de passe est obligatoire.
                    ->revealable()
                    ->minLength(8) // Ajoutez des règles de validation (minimum 8 caractères)
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)) // Hachage (Crucial!)
                    ->dehydrated(fn (?string $state) => filled($state)), // S'assure que le champ n'est pas sauvegardé vide lors de l'édition

                TextInput::make('password_confirmation')
                    ->label('Confirmer le mot de passe')
                    ->password()
                    ->revealable()
                    ->required(),
                // Utiliser la règle de validation Laravel 'same'

                Select::make('type')
                    ->options(UserType::class)
                    ->native(false)
                    ->required(),
                Select::make('role')
                    ->options(UserRole::class)
                    ->native(false)
                    ->required(),
                Select::make('city_id')
                    ->relationship('city', 'name')
                    ->required()
                    ->searchable()
                    ->placeholder('Choisir une ville')
                    ->searchDebounce(250)
                    ->preload()
                    ->suffixIcon(Heroicon::HomeModern)
                    ->loadingMessage('Chargement des villes...')
                    ->noSearchResultsMessage('Aucun résultat trouvé')
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->modifyQueryUsing(fn( $query) => $query->where('role', 'agent'))
            ->heading('Utilisateurs')
            ->description('Liste des utilisateurs')
            ->deferLoading()
            ->striped()
            ->extremePaginationLinks()
            ->recordTitleAttribute('firstname')
            ->columns([
                ImageColumn::make('avatar')->label('Avatar')
                    ->circular()
                    ->size(40)
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->formatStateUsing(function ($record) {
                        return $record->firstname.' '.$record->lastname;
                    })->searchable()->sortable(),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone number copied to clipboard!')
                    ->copyMessageDuration(1500),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied to clipboard!')
                    ->copyMessageDuration(1500),
                TextColumn::make('email_verified_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->visible(false),
                TextColumn::make('role')
                    ->badge()
                    ->searchable()
                    ->visible(false),
                TextColumn::make('city.name')->label('Ville')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->isoDateTime('LLLL', 'Europe/Paris')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('deleted_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                TrashedFilter::make(),
                Filter::make('is_active')->label('utilisateurs actifs')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('is_active', true)),
                SelectFilter::make('role')->label('Filter par role')
                    ->options([
                        'admin' => 'Admin',
                        'agent' => 'Agent',
                        'customer' => 'Customer',
                    ])->native(false),
                SelectFilter::make('type')->label('Filter par type')
                    ->options([
                        'individual' => 'Individual',
                        'Agency' => 'Agency',
                    ])->native(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->filters([
                SelectFilter::make('role')
                    ->options([
                        'customer' => 'Clients',
                        'agent' => 'Agents',
                        'admin' => 'Admins',
                    ])->native(false),
            ])->headerActions([
                ImportAction::make()->label('Importer')
                    ->importer(UserImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(UserExporter::class)
                    ->icon(Heroicon::ArrowDownTray)
                    ->formats([
                        ExportFormat::Csv,
                        ExportFormat::Xlsx,
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->label('Exporter')
                        ->exporter(UserExporter::class),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
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
        return 'The number of users';
    }
}
