<?php

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?string $recordTitleAttribute = 'firstname';

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
                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state)) // Hachage (Crucial!)
                    ->dehydrated(fn(?string $state) => filled($state)), // S'assure que le champ n'est pas sauvegardé vide lors de l'édition

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
            ->recordTitleAttribute('firstname')
            ->columns([
                ImageColumn::make('avatar')->label('Avatar')
                    ->circular()
                    ->size(40)
                    ->searchable(),
                TextColumn::make('firstname')
                    ->searchable(),
                TextColumn::make('lastname')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->searchable(),
                TextColumn::make('city_id')->label('city')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
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
}
