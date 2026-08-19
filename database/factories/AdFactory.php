<?php

namespace Database\Factories;

use App\Enums\PropertyAttribute;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @extends Factory<Ad> */
class AdFactory extends Factory
{
    protected static $citiesData = null;

    protected $model = Ad::class;

    private static array $descriptions = [
        'Beau logement disponible dans un quartier calme et bien sécurisé. Eau courante et électricité disponibles. Proche de toutes les commodités (marchés, transport, écoles). Idéal pour une famille ou un professionnel.',
        'Appartement propre et lumineux, entièrement carrelé, peinture récente. Cuisine équipée, salon spacieux, chambres bien aérées. Gardiennage assuré. Disponible immédiatement.',
        'Logement de standing dans une résidence sécurisée. Finitions soignées, carrelage importé, sanitaires modernes. Parking privatif disponible. Titre foncier disponible sur demande.',
        'Chambre spacieuse et bien entretenue dans une concession propre. Accès eau et électricité 24h/24. Douche et WC internes. Quartier résidentiel, voisinage respectueux.',
        "Studio neuf entièrement rénové. Cuisine américaine, salle de bain moderne avec douche à l'italienne. Compteurs individuels eau et électricité. Proche des axes principaux.",
        'Villa de standing sur terrain clôturé. Salon double, salle à manger, cuisine équipée, chambres climatisées. Forage privé, groupe électrogène. Titre foncier disponible.',
        'Appartement meublé tout confort. Lit queen size, armoire, canapé, TV écran plat, climatisation, WiFi inclus. Cuisine complète. Disponible pour location courte ou longue durée.',
        'Local commercial idéalement situé en bord de route bitumée, forte visibilité. Surface utile de plain-pied, électricité triphasée, accès camion possible. Bail commercial disponible.',
        'Terrain constructible plat, viabilisé (eau et électricité en bordure). Titre foncier obtenu, bornage effectué. Environnement résidentiel en plein développement. Idéal pour villa.',
        'Maison de plain-pied bien construite. 3 chambres avec placards intégrés, salon lumineux, cuisine équipée, 2 salles de bain. Cour intérieure, parking 2 véhicules. Quartier calme.',
    ];

    public function definition(): array
    {
        $cityData = $this->getCitiesData();
        $latitude = $cityData['latitude'];
        $longitude = $cityData['longitude'];
        $cityName = $cityData['name'];
        $address = 'Quartier '.$cityName.', '.fake()->randomElement(['Rue des Palmiers', 'Avenue Kennedy', 'Rue Moukoundé', 'Boulevard de la République', 'Avenue de Gaulle', 'Rue Mbalmayo']);

        $price = fake()->numberBetween(25000, 300000);
        $hasForf = fake()->boolean(40);

        $title = fake()->randomElement([
            "Appartement moderne à {$cityName}",
            "Studio meublé disponible – {$cityName}",
            "Chambre propre à louer – {$cityName}",
            "Villa {$cityName} avec parking",
            "Local commercial {$cityName} bord de route",
            "Terrain à vendre – {$cityName}",
            "Maison 3 chambres – {$cityName}",
        ]);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.strtolower(Str::random(6)),
            'description' => fake()->randomElement(self::$descriptions),
            'adresse' => $address,
            'price' => $price,
            'surface_area' => fake()->numberBetween(15, 300),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->numberBetween(1, 3),
            'has_parking' => fake()->boolean(40),
            'location' => Point::makeGeodetic($latitude, $longitude),
            'status' => fake()->randomElement(['available', 'available', 'available', 'reserved', 'rent']),
            'is_visible' => true,
            'available_from' => fake()->optional(0.3)->dateTimeBetween('now', '+2 weeks'),
            'available_to' => fake()->optional(0.2)->dateTimeBetween('+1 month', '+1 year'),
            'attributes' => fake()->optional(0.8)->randomElements(
                array_column(PropertyAttribute::cases(), 'value'),
                fake()->numberBetween(3, 7)
            ),
            'deposit_amount' => fake()->optional(0.7)->randomElement([
                '1 mois de caution',
                '2 mois de caution',
                '1 mois de loyer d\'avance',
                'Caution : '.number_format($price, 0, ',', ' ').' FCFA',
            ]),
            'minimum_lease_duration' => fake()->optional(0.7)->randomElement([
                '1 mois renouvelable', '3 mois renouvelables', '6 mois ferme',
                '1 an renouvelable', 'Mensuel, sans engagement',
            ]),
            'charges_forfaitaires' => $hasForf,
            'charges_montant_forfait' => $hasForf ? fake()->numberBetween(5, 25) * 1000 : null,
            'charges_eau' => !$hasForf && fake()->boolean(70) ? fake()->numberBetween(2, 8) * 1000 : null,
            'charges_electricite' => !$hasForf && fake()->boolean(70) ? fake()->numberBetween(3, 15) * 1000 : null,
            'charges_autres' => !$hasForf && fake()->boolean(60)
                ? fake()->randomElement([
                    'Gardiennage : 5 000 FCFA/mois, Enlèvement ordures : 2 000 FCFA/mois',
                    'Gardiennage : 8 000 FCFA/mois, Entretien communs : 3 000 FCFA/mois',
                    'Poubelle : 1 500 FCFA/mois, Gardien de nuit : 4 000 FCFA/mois',
                    'Sécurité : 6 000 FCFA/mois, Nettoyage : 2 500 FCFA/mois',
                    'Gardiennage : 10 000 FCFA/mois',
                ])
                : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory()->agents(),
            'quarter_id' => Quarter::factory(),
            'type_id' => AdType::inRandomOrder()->first()->id ?? AdType::factory(),
        ];
    }

    protected function getCitiesData(): array
    {
        if (self::$citiesData === null) {
            // Jeu minimal réservé aux factories/tests. Le catalogue applicatif
            // réel est exclusivement alimenté par geo:refresh-osm.
            self::$citiesData = [
                ['name' => 'Douala', 'latitude' => 4.0511, 'longitude' => 9.7679],
                ['name' => 'Yaoundé', 'latitude' => 3.8667, 'longitude' => 11.5167],
                ['name' => 'Bafoussam', 'latitude' => 5.4737, 'longitude' => 10.4179],
                ['name' => 'Bamenda', 'latitude' => 5.9597, 'longitude' => 10.1597],
                ['name' => 'Kribi', 'latitude' => 2.95, 'longitude' => 9.9167],
            ];
        }

        // Retourne une ville aléatoire
        return self::$citiesData[array_rand(self::$citiesData)];
    }
}
