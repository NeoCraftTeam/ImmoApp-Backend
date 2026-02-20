<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Property attributes enum for ad amenities and features.
 */
enum PropertyAttribute: string implements HasLabel
{
    case Wifi = 'wifi';
    case AirConditioning = 'air_conditioning';
    case Heating = 'heating';
    case PetsAllowed = 'pets_allowed';
    case Furnished = 'furnished';
    case Pool = 'pool';
    case Garden = 'garden';
    case Balcony = 'balcony';
    case Terrace = 'terrace';
    case Elevator = 'elevator';
    case Security = 'security';
    case Gym = 'gym';
    case Laundry = 'laundry';
    case Storage = 'storage';
    case Fireplace = 'fireplace';
    case Dishwasher = 'dishwasher';
    case WashingMachine = 'washing_machine';
    case Tv = 'tv';
    case Accessibility = 'accessibility';
    case SmokingAllowed = 'smoking_allowed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wifi => 'Wi-Fi',
            self::AirConditioning => 'Climatisation',
            self::Heating => 'Chauffage',
            self::PetsAllowed => 'Animaux acceptés',
            self::Furnished => 'Meublé',
            self::Pool => 'Piscine',
            self::Garden => 'Jardin',
            self::Balcony => 'Balcon',
            self::Terrace => 'Terrasse',
            self::Elevator => 'Ascenseur',
            self::Security => 'Sécurité 24h',
            self::Gym => 'Salle de sport',
            self::Laundry => 'Buanderie',
            self::Storage => 'Espace de rangement',
            self::Fireplace => 'Cheminée',
            self::Dishwasher => 'Lave-vaisselle',
            self::WashingMachine => 'Machine à laver',
            self::Tv => 'Télévision',
            self::Accessibility => 'Accessible PMR',
            self::SmokingAllowed => 'Fumeurs acceptés',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Wifi => 'heroicon-o-wifi',
            self::AirConditioning => 'heroicon-o-cloud',
            self::Heating => 'heroicon-o-fire',
            self::PetsAllowed => 'heroicon-o-heart',
            self::Furnished => 'heroicon-o-home-modern',
            self::Pool => 'heroicon-o-beaker',
            self::Garden => 'heroicon-o-sun',
            self::Balcony => 'heroicon-o-square-2-stack',
            self::Terrace => 'heroicon-o-squares-2x2',
            self::Elevator => 'heroicon-o-arrows-up-down',
            self::Security => 'heroicon-o-shield-check',
            self::Gym => 'heroicon-o-trophy',
            self::Laundry => 'heroicon-o-archive-box',
            self::Storage => 'heroicon-o-cube',
            self::Fireplace => 'heroicon-o-fire',
            self::Dishwasher => 'heroicon-o-sparkles',
            self::WashingMachine => 'heroicon-o-cog-6-tooth',
            self::Tv => 'heroicon-o-tv',
            self::Accessibility => 'heroicon-o-user',
            self::SmokingAllowed => 'heroicon-o-no-symbol',
        };
    }

    /**
     * Get all attributes as array for API responses.
     *
     * @return array<string, array{value: string, label: string, icon: string}>
     */
    public static function toSelectArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'icon' => $case->getIcon(),
            ];
        }

        return $result;
    }
}
