<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributes\Schemas;

use App\Models\PropertyAttributeCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PropertyAttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label('Identifiant')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Utilisé dans le code et l\'API'),
                Select::make('property_attribute_category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Créez une nouvelle catégorie avec le bouton +, ou utilisez le menu "Catégories attributs".')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label('Identifiant')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: PropertyAttributeCategory::class, column: 'slug'),
                    ]),
                Hidden::make('icon')
                    ->default('CheckCircleOutline'),
                Hidden::make('admin_icon')
                    ->default('heroicon-o-check-circle'),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->helperText('Les attributs inactifs ne sont pas affichés dans les formulaires')
                    ->default(true),
            ]);
    }
}
