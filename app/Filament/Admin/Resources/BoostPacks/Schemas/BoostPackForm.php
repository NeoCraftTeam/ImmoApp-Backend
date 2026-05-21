<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BoostPackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du pack boost')
                    ->icon(Heroicon::Bolt)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom du pack')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Pack Starter'),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->placeholder('pack-starter')
                            ->helperText('Identifiant URL unique (minuscules, tirets)'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Idéal pour booster une annonce 7 jours et toucher plus de locataires potentiels.'),

                        TextInput::make('reach_description')
                            ->label('Description de la portée')
                            ->maxLength(255)
                            ->placeholder('Touche jusqu\'à 2 000 personnes')
                            ->helperText('Texte affiché sur la carte du pack pour indiquer la portée estimée'),
                    ])
                    ->columns(2),

                Section::make('Paramètres du boost')
                    ->icon(Heroicon::ChartBar)
                    ->schema([
                        TextInput::make('duration_days')
                            ->label('Durée (jours)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->suffix('jours')
                            ->helperText('Pack 1 & 2 = 7 jours — Pack 3 = 30 jours'),

                        TextInput::make('boost_score')
                            ->label('Score de boost')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText('Score ajouté à l\'annonce dans le tri (1-100). Plus il est élevé, plus l\'annonce remonte haut.'),

                        TextInput::make('price_credits')
                            ->label('Prix (crédits)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->suffix('crédits')
                            ->helperText('Nombre de crédits débités du bailleur à l\'achat'),

                        TextInput::make('sort_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Ordre croissant (0 = en premier)'),
                    ])
                    ->columns(2),

                Section::make('Visibilité')
                    ->icon(Heroicon::Eye)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Pack actif')
                            ->default(true)
                            ->helperText('Les packs inactifs ne sont pas disponibles à l\'achat'),

                        Toggle::make('is_popular')
                            ->label('Mise en avant')
                            ->default(false)
                            ->helperText('Affiche le badge "Recommandé" sur la carte du pack'),
                    ]),
            ]);
    }
}
