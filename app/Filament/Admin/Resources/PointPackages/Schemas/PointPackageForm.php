<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PointPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du pack')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom du pack')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Pack Starter'),

                        TextInput::make('price')
                            ->label('Prix')
                            ->numeric()
                            ->required()
                            ->suffix('FCFA')
                            ->minValue(0),

                        TextInput::make('points_awarded')
                            ->label('Points accordés')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Nombre de points crédités à l\'achat'),

                        TextInput::make('sort_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Ordre croissant'),
                    ])
                    ->columns(2),

                Section::make('Paramètres')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Pack actif')
                            ->default(true)
                            ->helperText('Les packs inactifs ne sont pas visibles dans l\'application'),
                    ]),
            ]);
    }
}
