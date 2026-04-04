<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    private static array $comments = [
        // Très positifs (5 étoiles)
        "Logement excellent, exactement comme décrit dans l'annonce. Propriétaire très sympathique et disponible. Je recommande vivement sans hésitation.",
        "Appartement propre, bien aménagé et très bien situé. Accès facile, quartier calme et sécurisé. L'eau et l'électricité sont disponibles 24h/24. Très satisfait.",
        "Villa magnifique avec tout le confort moderne. Le gardien est présent la nuit, le parking est spacieux. Je n'ai rien à redire, c'est parfait pour une famille.",
        "Chambre propre et bien entretenue dans une concession calme. Le propriétaire est sérieux, réactif et respectueux. Voisinage agréable. Je reviendrai.",
        "Studio meublé de qualité, mobilier neuf, climatisation fonctionnelle. Internet inclus. L'annonce correspondait à 100% à la réalité. Très bonne expérience.",
        "Superbe appartement en duplex, finitions haut de gamme. Le quartier est résidentiel et très bien desservi par les transports. Tout est conforme aux photos.",
        "Bonne adresse, proximité des commerces et du marché. Eau courante, électricité stable. Le propriétaire nous a aidés pour l'installation. Très content.",
        "Logement idéal pour un professionnel. Calme, bien éclairé, cuisine bien équipée. La sécurité du quartier est rassurante. Je recommande à 100%.",
        "Terrain bien situé, titre foncier propre et documents en règle. Transaction transparente et rapide. Vendeur sérieux, aucun litige. Très bonne expérience.",
        "Maison spacieuse avec jardin, forage d'eau privé et groupe électrogène. Idéale pour une grande famille. Le prix est juste par rapport au standing.",
        // Positifs (4 étoiles)
        "Très bon logement dans l'ensemble. Quelques petits détails à finir dans la salle de bain, mais rien de grave. Le propriétaire a promis de régler ça rapidement.",
        "Appartement conforme à l'annonce. Bon rapport qualité-prix pour le quartier. Le seul bémol est le bruit de la route principale le matin.",
        "Bon séjour. Le logement est propre et fonctionnel. Propriétaire disponible sur WhatsApp pour tout problème. Je recommande.",
        "Chambre correcte et bien entretenue. Eau chaude disponible le matin. Le voisinage est calme. Légèrement loin du marché mais accessible en moto.",
        "Studio bien équipé, cuisine fonctionnelle, bonne ventilation naturelle. Quelques coupures d'électricité occasionnelles mais c'est la norme dans le quartier.",
        "Belle villa, bien construite. Le jardin nécessite un peu d'entretien mais l'ensemble est en bon état. Quartier résidentiel agréable.",
        "Logement dans une bonne résidence sécurisée. Gardiennage 24h/24. Parking couvert. L'appartement est propre et les voisins sont respectueux.",
        "Appartement meublé de bon standing. Lit confortable, TV écran plat, WiFi rapide. Quelques équipements de cuisine manquants mais globalement satisfait.",
        // Neutres (3 étoiles)
        "Logement correct sans plus. Le prix est légèrement élevé par rapport à ce qui est proposé. Quelques travaux de rénovation seraient bienvenus.",
        "Chambre propre mais un peu petite. La douche fonctionne bien, l'électricité est stable. Le quartier est bruyant la nuit à cause du bar voisin.",
        "Studio acceptable pour le prix demandé. La plomberie a quelques problèmes que le propriétaire a promis de réparer. À voir sur le long terme.",
        "Appartement conforme à la description mais les photos le montraient plus lumineux. Correct pour un court séjour ou une première installation.",
        "Maison spacieuse mais nécessitant quelques réparations (carrelage fissuré, peinture à refaire). Le propriétaire est de bonne volonté.",
        // Négatifs (2-3 étoiles)
        "Logement décevant par rapport aux photos. La cuisine n'était pas équipée comme indiqué. J'espère que le propriétaire va corriger l'annonce.",
        "Des problèmes de plomberie récurrents. L'eau chaude ne fonctionnait pas la moitié du temps. Propriétaire difficile à joindre pour les réparations.",
        "Quartier moins sécurisé que ce qui était annoncé. Quelques nuisances sonores la nuit. Le logement en lui-même est correct.",
    ];

    public function definition(): array
    {
        $rating = (float) fake()->randomElement([2, 3, 3, 4, 4, 4, 5, 5, 5, 5]);

        return [
            'rating'     => $rating,
            'comment'    => fake()->randomElement(self::$comments),
            'created_at' => Carbon::now()->subDays(mt_rand(0, 180)),
            'updated_at' => Carbon::now()->subDays(mt_rand(0, 180)),

            'ad_id'   => Ad::factory(),
            'user_id' => User::factory(),
        ];
    }
}
