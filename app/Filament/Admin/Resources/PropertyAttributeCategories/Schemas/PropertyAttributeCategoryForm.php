<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributeCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PropertyAttributeCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')
                ->label('Actif')
                ->default(true),
        ]);
    }
}
