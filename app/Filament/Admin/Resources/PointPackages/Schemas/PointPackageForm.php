<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PointPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du pack')
                    ->icon(Heroicon::Star)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom du pack')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Pack Starter'),

                        TextInput::make('description')
                            ->label('Description courte')
                            ->maxLength(255)
                            ->placeholder('Idéal pour découvrir la plateforme'),

                        TextInput::make('badge')
                            ->label('Badge')
                            ->maxLength(50)
                            ->placeholder('Le + populaire')
                            ->helperText('Texte du badge affiché sur la carte (ex: "Le + populaire", "Meilleur rapport")'),

                        TextInput::make('price')
                            ->label('Prix')
                            ->numeric()
                            ->required()
                            ->suffix('FCFA')
                            ->minValue(0),

                        TextInput::make('points_awarded')
                            ->label('Crédits accordés')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Nombre de crédits accordés à l\'achat'),

                        \Filament\Forms\Components\TagsInput::make('features')
                            ->label('Fonctionnalités')
                            ->placeholder('Ajouter une fonctionnalité')
                            ->helperText('Liste des avantages affichés sur la carte du pack'),

                        TextInput::make('sort_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Ordre croissant'),
                    ])
                    ->columns(2),

                Section::make('Paramètres')
                    ->icon(Heroicon::Cog6Tooth)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Pack actif')
                            ->default(true)
                            ->helperText('Les packs inactifs ne sont pas visibles dans l\'application'),
                        Toggle::make('is_popular')
                            ->label('Mise en avant')
                            ->default(false)
                            ->helperText('Met ce pack en avant avec un style différent (bordure accentuée)'),
                    ]),
            ]);
    }
}
