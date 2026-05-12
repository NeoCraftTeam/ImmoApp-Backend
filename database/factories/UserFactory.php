<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\City;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private static array $firstNamesFr = [
        'Armand', 'Boris', 'Cédric', 'David', 'Emmanuel', 'Franck', 'Gaston', 'Henri',
        'Jean-Pierre', 'Kevin', 'Laurent', 'Martin', 'Nicolas', 'Olivier', 'Patrick',
        'Raoul', 'Serge', 'Thomas', 'Urbain', 'Valentin', 'William', 'Xavier',
        'Alice', 'Brigitte', 'Clarisse', 'Diane', 'Estelle', 'Flore', 'Gisèle',
        'Hélène', 'Ingrid', 'Julie', 'Karine', 'Louise', 'Marie', 'Nadège',
        'Odile', 'Patricia', 'Rachel', 'Sandrine', 'Thérèse', 'Véronique',
        'Anastasie', 'Bertrand', 'Christophe', 'Désiré', 'Edith', 'Fidèle',
        'Ghislain', 'Honorine', 'Irène', 'Jacques', 'Laeticia', 'Madeleine',
        'Rodrigue', 'Stella', 'Théodore', 'Vanessa', 'Yvonne', 'Fabrice',
    ];

    private static array $lastNamesCm = [
        'Mbarga', 'Nkolo', 'Essomba', 'Mvondo', 'Ateba', 'Fouda', 'Biyong',
        'Ekotto', 'Mebande', 'Ntouba', 'Ondoa', 'Efoua', 'Mvilongo',
        'Nkengue', 'Owona', 'Abomo', 'Ndongo', 'Mengue', 'Zambo', 'Assoumou',
        'Medou', 'Ndzana', 'Bekono', 'Ebolo', 'Ngono', 'Oyono',
        'Tchamba', 'Youmbi', 'Kamga', 'Mbaye', 'Fotso', 'Kenne',
        'Ngadjeu', 'Teko', 'Djitieu', 'Fomekong', 'Tagne', 'Kouam',
        'Yonta', 'Zangue', 'Nanfah', 'Donfack', 'Tekam', 'Nguefack',
        'Noumsi', 'Wabo', 'Sokoudjou', 'Ngoula', 'Mangoua', 'Kenfack',
        'Simo', 'Nanga', 'Moto', 'Balla', 'Feukam', 'Djomo',
    ];

    private static array $agentBios = [
        "Agent immobilier professionnel avec plus de 5 ans d'expérience à Douala et Yaoundé. Spécialisé dans la location et la vente d'appartements, villas et terrains. À votre service 7j/7.",
        "Promoteur immobilier agréé, je vous accompagne dans tous vos projets de location et d'acquisition. Sérieux, réactif et disponible. Parc immobilier varié dans les meilleurs quartiers.",
        "Expert en immobilier depuis 8 ans sur l'axe Douala-Yaoundé. Annonces vérifiées, visites rapides. Je m'engage à vous trouver le bien idéal au meilleur prix.",
        'Gestionnaire de biens immobiliers. Je propose des logements de standing et des propriétés résidentielles. Toutes mes annonces sont authentiques avec titre foncier disponible.',
        'Conseiller immobilier certifié. Spécialités : appartements meublés, villas et locaux commerciaux. Accompagnement complet de A à Z pour chaque transaction.',
        'Agent immobilier indépendant opérant à Douala, Bafoussam et environs. Portfolio de 50+ biens disponibles. Réponse rapide garantie.',
        'Promoteur et investisseur immobilier. Vente et location de maisons, appartements et terrains dans les quartiers résidentiels. Documents légaux garantis.',
        "Agence immobilière familiale avec 12 ans d'expérience. Nous gérons votre patrimoine immobilier avec soin et transparence. Visites disponibles tous les jours.",
    ];

    private static array $customerBios = [
        "Cadre d'entreprise à la recherche d'un logement de standing. Solvable, non-fumeur. Références disponibles sur demande.",
        'Fonctionnaire en poste. Cherche appartement ou villa pour ma famille. Paiement ponctuel garanti.',
        "Ingénieur à la recherche d'un logement moderne proche de mon lieu de travail. Sérieux et respectueux.",
        'Entrepreneur cherchant un local commercial ou bureau bien situé. Budget disponible, visites possibles immédiatement.',
        'Enseignante cherchant logement propre et sécurisé pour ma famille. Dossier complet disponible.',
        'Professionnel de santé recherchant appartement ou villa de standing. Disponible rapidement.',
        'Cadre bancaire cherchant logement calme et bien desservi. Non-fumeur, sans animaux.',
        'Responsable commercial cherchant appartement meublé pour installation rapide. Solvable.',
    ];

    public function definition(): array
    {
        $firstname = fake()->randomElement(self::$firstNamesFr);
        $lastname = fake()->randomElement(self::$lastNamesCm);
        // No ADMIN in default pool — only customer (75%) or agent (25%)
        $role = fake()->randomElement([UserRole::CUSTOMER, UserRole::CUSTOMER, UserRole::CUSTOMER, UserRole::AGENT]);
        $isAgent = $role === UserRole::AGENT;

        [$lat, $lng] = fake()->randomElement([
            [3.8667, 11.5167],
            [4.0511, 9.7679],
            [5.4737, 10.4179],
            [5.9597, 10.1597],
            [2.9500, 9.9167],
            [4.1500, 9.2333],
        ]);

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'username' => Str::lower(Str::ascii($firstname)).'_'.Str::lower(Str::ascii($lastname)).mt_rand(10, 9999),
            'bio' => $isAgent
                ? fake()->randomElement(self::$agentBios)
                : fake()->randomElement(self::$customerBios),
            'avatar' => null,
            'role' => $role,
            'type' => $isAgent ? fake()->randomElement(UserType::cases()) : null,
            'phone_number' => self::cameroonPhone(),
            'phone_is_whatsapp' => true,
            'email' => fake()->unique()->userName().'@'.fake()->randomElement(['gmail.com', 'yahoo.fr', 'hotmail.com', 'outlook.com']),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'location' => "POINT($lng $lat)",
            'city_id' => City::factory(),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function agents(): Factory|UserFactory
    {
        return $this->state([
            'role' => UserRole::AGENT,
            'type' => fake()->randomElement(UserType::cases()),
            'bio' => fake()->randomElement(self::$agentBios),
        ]);
    }

    public function admin(): Factory|UserFactory
    {
        return $this->state([
            'role' => UserRole::ADMIN,
            'type' => null,
            'bio' => null,
        ]);
    }

    public function customers(): Factory|UserFactory
    {
        return $this->state([
            'role' => UserRole::CUSTOMER,
            'type' => null,
            'bio' => fake()->randomElement(self::$customerBios),
        ]);
    }

    public static function cameroonPhone(): string
    {
        $prefixes = ['650', '651', '652', '653', '655', '670', '671', '672', '675', '677', '680', '690', '691', '695', '696', '697', '699'];
        $prefix = $prefixes[array_rand($prefixes)];
        $num = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

        return '+237 '.$prefix.' '.substr($num, 0, 3).' '.substr($num, 3, 3);
    }
}
