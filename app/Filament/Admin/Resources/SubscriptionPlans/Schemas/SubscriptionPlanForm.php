<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom du plan')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('price')
                            ->label('Prix Mensuel')
                            ->numeric()
                            ->suffix('FCFA')
                            ->required(),

                        TextInput::make('price_yearly')
                            ->label('Prix Annuel')
                            ->numeric()
                            ->suffix('FCFA')
                            ->helperText('Laissez vide si non disponible'),

                        TextInput::make('duration_days')
                            ->label('Durée (jours)')
                            ->required()
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->suffix('jours'),
                    ])
                    ->columns(2),

                Section::make('Configuration du Boost')
                    ->schema([
                        TextInput::make('boost_score')
                            ->label('Score de boost')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Score ajouté aux annonces (0-100)'),

                        TextInput::make('boost_duration_days')
                            ->label('Durée du boost (jours)')
                            ->required()
                            ->numeric()
                            ->default(7)
                            ->minValue(1)
                            ->suffix('jours')
                            ->helperText('Durée du boost par annonce'),

                        TextInput::make('max_ads')
                            ->label('Nombre max d\'annonces')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Laisser vide pour illimité'),
                    ])
                    ->columns(3),

                Section::make('Fonctionnalités')
                    ->schema([
                        KeyValue::make('features')
                            ->label('Liste des fonctionnalités')
                            ->keyLabel('Fonctionnalité')
                            ->valueLabel('Description')
                            ->reorderable()
                            ->addActionLabel('Ajouter une fonctionnalité'),
                    ]),

                Section::make('Paramètres')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Plan actif')
                            ->default(true)
                            ->helperText('Les plans inactifs ne sont pas visibles'),

                        TextInput::make('sort_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Ordre croissant'),
                    ])
                    ->columns(2),
            ]);
    }
}
