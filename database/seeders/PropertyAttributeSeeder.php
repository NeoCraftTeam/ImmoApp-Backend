<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PropertyAttribute;
use Illuminate\Database\Seeder;

class PropertyAttributeSeeder extends Seeder
{
    /**
     * Seed default property attributes.
     */
    public function run(): void
    {
        $attributes = [
            ['name' => 'Wi-Fi', 'slug' => 'wifi', 'icon' => 'heroicon-o-wifi', 'sort_order' => 1],
            ['name' => 'Climatisation', 'slug' => 'air_conditioning', 'icon' => 'heroicon-o-cloud', 'sort_order' => 2],
            ['name' => 'Chauffage', 'slug' => 'heating', 'icon' => 'heroicon-o-fire', 'sort_order' => 3],
            ['name' => 'Animaux acceptés', 'slug' => 'pets_allowed', 'icon' => 'heroicon-o-heart', 'sort_order' => 4],
            ['name' => 'Meublé', 'slug' => 'furnished', 'icon' => 'heroicon-o-home-modern', 'sort_order' => 5],
            ['name' => 'Piscine', 'slug' => 'pool', 'icon' => 'heroicon-o-beaker', 'sort_order' => 6],
            ['name' => 'Jardin', 'slug' => 'garden', 'icon' => 'heroicon-o-sun', 'sort_order' => 7],
            ['name' => 'Balcon', 'slug' => 'balcony', 'icon' => 'heroicon-o-square-2-stack', 'sort_order' => 8],
            ['name' => 'Terrasse', 'slug' => 'terrace', 'icon' => 'heroicon-o-squares-2x2', 'sort_order' => 9],
            ['name' => 'Ascenseur', 'slug' => 'elevator', 'icon' => 'heroicon-o-arrows-up-down', 'sort_order' => 10],
            ['name' => 'Sécurité 24h', 'slug' => 'security', 'icon' => 'heroicon-o-shield-check', 'sort_order' => 11],
            ['name' => 'Salle de sport', 'slug' => 'gym', 'icon' => 'heroicon-o-trophy', 'sort_order' => 12],
            ['name' => 'Buanderie', 'slug' => 'laundry', 'icon' => 'heroicon-o-archive-box', 'sort_order' => 13],
            ['name' => 'Espace de rangement', 'slug' => 'storage', 'icon' => 'heroicon-o-cube', 'sort_order' => 14],
            ['name' => 'Cheminée', 'slug' => 'fireplace', 'icon' => 'heroicon-o-fire', 'sort_order' => 15],
            ['name' => 'Lave-vaisselle', 'slug' => 'dishwasher', 'icon' => 'heroicon-o-sparkles', 'sort_order' => 16],
            ['name' => 'Machine à laver', 'slug' => 'washing_machine', 'icon' => 'heroicon-o-cog-6-tooth', 'sort_order' => 17],
            ['name' => 'Télévision', 'slug' => 'tv', 'icon' => 'heroicon-o-tv', 'sort_order' => 18],
            ['name' => 'Accessible PMR', 'slug' => 'accessibility', 'icon' => 'heroicon-o-user', 'sort_order' => 19],
            ['name' => 'Fumeurs acceptés', 'slug' => 'smoking_allowed', 'icon' => 'heroicon-o-no-symbol', 'sort_order' => 20],
        ];

        foreach ($attributes as $attribute) {
            PropertyAttribute::query()->updateOrCreate(
                ['slug' => $attribute['slug']],
                $attribute
            );
        }
    }
}
