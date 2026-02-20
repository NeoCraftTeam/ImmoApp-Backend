<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributes\Schemas;

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
                Select::make('icon')
                    ->label('Icône')
                    ->options(self::getIconOptions())
                    ->searchable()
                    ->required()
                    ->default('heroicon-o-check-circle'),
                TextInput::make('sort_order')
                    ->label('Ordre d\'affichage')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->helperText('Les attributs inactifs ne sont pas affichés dans les formulaires')
                    ->default(true),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function getIconOptions(): array
    {
        return [
            'heroicon-o-wifi' => 'Wi-Fi',
            'heroicon-o-cloud' => 'Cloud / Climatisation',
            'heroicon-o-fire' => 'Feu / Chauffage',
            'heroicon-o-truck' => 'Véhicule / Parking',
            'heroicon-o-heart' => 'Cœur / Animaux',
            'heroicon-o-home-modern' => 'Maison moderne / Meublé',
            'heroicon-o-beaker' => 'Bécher / Piscine',
            'heroicon-o-sun' => 'Soleil / Jardin',
            'heroicon-o-square-2-stack' => 'Carrés / Balcon',
            'heroicon-o-squares-2x2' => 'Grille / Terrasse',
            'heroicon-o-arrows-up-down' => 'Flèches / Ascenseur',
            'heroicon-o-shield-check' => 'Bouclier / Sécurité',
            'heroicon-o-trophy' => 'Trophée / Sport',
            'heroicon-o-archive-box' => 'Boîte / Buanderie',
            'heroicon-o-cube' => 'Cube / Rangement',
            'heroicon-o-sparkles' => 'Étoiles / Lave-vaisselle',
            'heroicon-o-cog-6-tooth' => 'Engrenage / Machine',
            'heroicon-o-tv' => 'TV / Télévision',
            'heroicon-o-user' => 'Utilisateur / Accessibilité',
            'heroicon-o-no-symbol' => 'Interdit / Fumeur',
            'heroicon-o-check-circle' => 'Cercle coché (défaut)',
            'heroicon-o-star' => 'Étoile',
            'heroicon-o-bolt' => 'Éclair',
            'heroicon-o-building-office' => 'Immeuble',
            'heroicon-o-key' => 'Clé',
        ];
    }
}
