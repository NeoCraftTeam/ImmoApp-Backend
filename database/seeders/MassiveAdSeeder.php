<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MassiveAdSeeder extends Seeder
{
    private const TOTAL_ADS = 2000;

    private const IMAGE_POOL_SIZE = 100;

    private const IMAGES_PER_AD = 5;

    private string $imageDir;

    /** @var string[] */
    private array $quarterIds = [];

    /** @var array<string, string> */
    private array $quarterNames = [];

    /** @var string[] */
    private array $agentIds = [];

    /** @var array<string, string> */
    private array $typeMap = [];

    /** @var array<string, string|null> */
    private array $agencyMap = [];

    /** @var array<string, array{lat: float, lng: float}> */
    private array $quarterCoords = [];

    /** @var string[] */
    private array $propertyNames = [
        'Marc Henri', 'Crystal', 'Les Palmiers', 'Le Bonheur',
        'Les Jardins', 'Royal', 'Prestige', 'Atlantic', 'Paradis',
        'Les Roses', 'Saphir', 'Le Soleil',
        'Les Cocotiers', 'La Grace', 'Belle Vue', 'Les Oliviers', 'Montana',
        'Victoria', 'Sainte Famille', 'Les Acacias', 'Le Rocher', 'Les Collines',
        'Le Diamant', 'Le Manoir', 'La Citadelle', 'Les Jasmins',
        'Le Versailles', 'Gloria', 'Neptune', 'Eden', 'Horizon',
    ];

    /** @var array<string, array{0: int, 1: int}> */
    private array $priceRanges = [
        'chambre simple' => [15000, 35000],
        'chambre meublee' => [25000, 55000],
        'studio simple' => [30000, 65000],
        'studio meuble' => [45000, 90000],
        'appartement simple' => [50000, 200000],
        'appartement meuble' => [75000, 350000],
        'maison' => [100000, 800000],
        'terrain' => [2000000, 50000000],
    ];

    public function run(): void
    {
        $this->imageDir = storage_path('app/seed-images');
        $this->command->info('Seeding ' . self::TOTAL_ADS . ' realistic ads with images...');
        $this->createUsers();
        $this->loadReferenceData();
        $this->downloadImagePool();
        $this->createAds();
        $this->cleanupImagePool();
        $this->command->info('Seeding complete! ' . Ad::count() . ' ads in database.');
    }

    private function createUsers(): void
    {
        $this->command->info('Creating users...');
        $password = Hash::make('password');
        $cities = City::all();

        User::factory()->admin()->recycle($cities)->create([
            'email' => 'admin@keyhome.cm',
            'password' => $password,
        ]);

        $agencyAgents = User::factory()
            ->count(20)->agents()->state(['type' => UserType::AGENCY])
            ->recycle($cities)->create(['password' => $password]);

        User::factory()
            ->count(20)->agents()->state(['type' => UserType::INDIVIDUAL])
            ->recycle($cities)->create(['password' => $password]);

        $agencyAgents->each(function (User $agent): void {
            $agency = Agency::factory()->create(['owner_id' => $agent->id]);
            $this->agencyMap[$agent->id] = $agency->id;
        });

        User::factory()->count(50)->customers()->recycle($cities)->create(['password' => $password]);
        $this->command->info('  1 admin, 40 agents (20 agences), 50 clients');
    }

    private function loadReferenceData(): void
    {
        $coordsPath = storage_path('app/quarter_coordinates.json');
        if (file_exists($coordsPath)) {
            $this->quarterCoords = json_decode(file_get_contents($coordsPath), true);
        }

        $quarters = Quarter::with('city')->get();
        $this->quarterIds = $quarters->pluck('id')->toArray();
        $this->quarterNames = $quarters->mapWithKeys(
            fn (Quarter $q) => [$q->id => $q->name . ', ' . $q->city->name]
        )->toArray();

        $this->agentIds = User::where('role', UserRole::AGENT)->pluck('id')->toArray();
        $this->typeMap = AdType::pluck('id', 'name')->toArray();

        if (empty($this->agencyMap)) {
            foreach (Agency::all() as $agency) {
                $this->agencyMap[$agency->owner_id] = $agency->id;
            }
        }

        $this->command->info('  ' . count($this->quarterIds) . ' quarters, ' . count($this->agentIds) . ' agents, ' . count($this->typeMap) . ' types');
    }

    private function downloadImagePool(): void
    {
        File::ensureDirectoryExists($this->imageDir);

        $existing = count(glob($this->imageDir . '/*.jpg'));
        if ($existing >= self::IMAGE_POOL_SIZE) {
            $this->command->info("Image pool already has {$existing} images, skipping.");
            return;
        }

        $this->command->info('Downloading ' . self::IMAGE_POOL_SIZE . ' images from Picsum...');
        $progress = $this->command->getOutput()->createProgressBar(self::IMAGE_POOL_SIZE);
        $progress->start();

        for ($i = 1; $i <= self::IMAGE_POOL_SIZE; $i++) {
            $path = $this->imageDir . '/' . $i . '.jpg';
            if (file_exists($path) && filesize($path) > 1000) {
                $progress->advance();
                continue;
            }

            $retries = 3;
            while ($retries > 0) {
                try {
                    $url = "https://picsum.photos/seed/keyhome{$i}/800/600";
                    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
                    $content = @file_get_contents($url, false, $ctx);
                    if ($content !== false && strlen($content) > 1000) {
                        file_put_contents($path, $content);
                        break;
                    }
                } catch (\Exception $e) {
                    // retry
                }
                $retries--;
                usleep(500000);
            }
            $progress->advance();
        }

        $progress->finish();
        $this->command->newLine();
        $this->command->info('  ' . count(glob($this->imageDir . '/*.jpg')) . ' images ready');
    }

    private function createAds(): void
    {
        $this->command->info('Creating ' . self::TOTAL_ADS . ' ads with images...');

        $typeNames = array_keys($this->typeMap);
        $imageFiles = glob($this->imageDir . '/*.jpg');
        $imageCount = count($imageFiles);

        if ($imageCount === 0) {
            $this->command->error('No images available!');
            return;
        }

        $this->command->info("  Using {$imageCount} images from pool");

        $progress = $this->command->getOutput()->createProgressBar(self::TOTAL_ADS);
        $progress->start();

        $perType = (int) ceil(self::TOTAL_ADS / count($typeNames));
        $created = 0;
        $imageErrors = 0;

        Ad::withoutSyncingToSearch(function () use ($typeNames, $perType, $imageFiles, &$created, &$imageErrors, $progress): void {
            foreach ($typeNames as $typeName) {
                $count = min($perType, self::TOTAL_ADS - $created);
                $normalizedType = $this->normalizeTypeName($typeName);

                for ($i = 0; $i < $count; $i++) {
                    $quarterId = $this->quarterIds[array_rand($this->quarterIds)];
                    $quarterLabel = $this->quarterNames[$quarterId] ?? 'Douala';
                    $agentId = $this->agentIds[array_rand($this->agentIds)];

                    $coords = $this->quarterCoords[$quarterId] ?? ['lat' => 4.05, 'lng' => 9.7];
                    $lat = $coords['lat'] + (mt_rand(-300, 300) / 100000);
                    $lng = $coords['lng'] + (mt_rand(-300, 300) / 100000);

                    $bedrooms = $this->bedroomsForType($normalizedType);
                    $surface = $this->surfaceForType($normalizedType);
                    $title = $this->generateTitle($normalizedType, $quarterLabel, $bedrooms, $surface);
                    $description = $this->generateDescription($normalizedType, $quarterLabel, $bedrooms, $surface);
                    $priceRange = $this->priceRanges[$normalizedType] ?? [25000, 200000];

                    $daysAgo = mt_rand(1, 120);

                    $ad = Ad::forceCreate([
                        'id' => (string) Str::orderedUuid(),
                        'title' => $title,
                        'slug' => Str::slug($title) . '-' . Str::random(6),
                        'description' => $description,
                        'adresse' => $quarterLabel,
                        'price' => mt_rand($priceRange[0], $priceRange[1]),
                        'surface_area' => $surface,
                        'bedrooms' => $bedrooms,
                        'bathrooms' => max(1, (int) round($bedrooms * 0.7)),
                        'has_parking' => in_array($normalizedType, ['maison', 'appartement meuble', 'appartement simple']),
                        'location' => "POINT({$lng} {$lat})",
                        'status' => $this->randomStatus(),
                        'user_id' => $agentId,
                        'quarter_id' => $quarterId,
                        'type_id' => $this->typeMap[$typeName],
                        'agency_id' => $this->agencyMap[$agentId] ?? null,
                        'created_at' => now()->subDays($daysAgo),
                        'updated_at' => now()->subDays($daysAgo),
                    ]);

                    $usedIndexes = [];
                    for ($img = 0; $img < self::IMAGES_PER_AD; $img++) {
                        try {
                            do {
                                $idx = array_rand($imageFiles);
                            } while (in_array($idx, $usedIndexes) && count($usedIndexes) < $imageCount);
                            $usedIndexes[] = $idx;

                            $ad->addMedia($imageFiles[$idx])
                                ->preservingOriginal()
                                ->toMediaCollection('images');
                        } catch (\Exception $e) {
                            $imageErrors++;
                        }
                    }

                    $created++;
                    $progress->advance();
                }
            }
        });

        $progress->finish();
        $this->command->newLine();
        $msg = "  {$created} ads created";
        if ($imageErrors > 0) {
            $msg .= " ({$imageErrors} image errors)";
        }
        $this->command->info($msg);
    }

    private function normalizeTypeName(string $name): string
    {
        $map = [
            'chambre simple' => 'chambre simple',
            'chambre meublee' => 'chambre meublee',
            'studio simple' => 'studio simple',
            'studio meuble' => 'studio meuble',
            'appartement simple' => 'appartement simple',
            'appartement meuble' => 'appartement meuble',
            'maison' => 'maison',
            'terrain' => 'terrain',
        ];

        $lower = mb_strtolower($name);
        $normalized = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'ù', 'û', 'ô', 'î', 'ï', 'ç'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'u', 'u', 'o', 'i', 'i', 'c'],
            $lower
        );

        return $map[$normalized] ?? $normalized;
    }

    private function randomStatus(): string
    {
        $w = ['available', 'available', 'available', 'available', 'available', 'reserved', 'rent'];
        return $w[array_rand($w)];
    }

    private function generateTitle(string $type, string $quarter, int $bedrooms, int $surface): string
    {
        $name = $this->propertyNames[array_rand($this->propertyNames)];
        $short = explode(',', $quarter)[0];

        return match ($type) {
            'chambre simple' => $this->pick([
                "Chambre Simple {$short}",
                "Chambre Individuelle {$short}",
                "Chambre Standard {$short}",
                "Belle Chambre {$short}",
                "Chambre Propre {$short}",
            ]),
            'chambre meublee' => $this->pick([
                "Chambre Meublee VIP {$short}",
                "Chambre Meublee Climatisee {$short}",
                "Chambre Tout Confort {$short}",
                "Chambre Standing {$short}",
                "Chambre Equipee {$short}",
            ]),
            'studio simple' => $this->pick([
                "Studio Neuf {$short}",
                "Studio 1 Piece {$short}",
                "Joli Studio {$short}",
                "Studio Moderne {$short}",
                "Studio {$short}",
            ]),
            'studio meuble' => $this->pick([
                "Studio Meuble VIP {$short}",
                "Studio Meuble Grand Standing {$short}",
                "Studio Tout Equipe {$short}",
                "Studio Meuble Climatise {$short}",
                "Studio Luxe {$short}",
            ]),
            'appartement simple' => $this->pick([
                "Appartement {$bedrooms} Chambres {$short}",
                "Bel Appartement {$bedrooms}P {$short}",
                "Residence {$name} - Appart {$bedrooms}P",
                "Appartement Neuf {$bedrooms}P {$short}",
                "Appartement Spacieux {$bedrooms}Ch {$short}",
            ]),
            'appartement meuble' => $this->pick([
                "Residence {$name} - Appart Meuble {$bedrooms}P",
                "Appartement Meuble Standing {$bedrooms}Ch {$short}",
                "Appart VIP Tout Meuble {$short}",
                "Appartement Meuble Haut Standing {$bedrooms}P",
                "Residence {$name} - {$bedrooms}P Meublees",
            ]),
            'maison' => $this->pick([
                "Villa {$name}",
                "Villa {$name} - {$short}",
                "Villa Duplex {$bedrooms}Ch {$short}",
                "Belle Villa Moderne {$bedrooms}Ch {$short}",
                "Villa Standing {$bedrooms} Chambres {$short}",
                "Duplex de Luxe {$bedrooms}Ch {$short}",
                "Residence {$name} - Villa {$bedrooms}Ch",
                "Maison {$bedrooms} Chambres {$short}",
                "Villa {$name} avec Piscine",
            ]),
            'terrain' => $this->pick([
                "Terrain {$surface}m2 {$short}",
                "Parcelle Titree {$surface}m2 {$short}",
                "Terrain Constructible {$surface}m2 {$short}",
                "Terrain a Vendre {$surface}m2 {$short}",
                "Lot {$surface}m2 {$short}",
                "Terrain Plat {$surface}m2 {$short}",
            ]),
            default => "Propriete {$short}",
        };
    }

    private function generateDescription(string $type, string $quarter, int $bedrooms, int $surface): string
    {
        return match ($type) {
            'chambre simple' => $this->pick([
                "Belle chambre disponible a {$quarter}. Cadre propre et securise, acces eau et electricite 24h/24. Proche de toutes commodites.",
                "Chambre spacieuse et aeree a louer a {$quarter}. Quartier calme et residentiel. Douche interne, WC propre.",
                "Chambre en excellent etat dans une concession bien entretenue a {$quarter}. Entree libre, voisinage respectueux.",
                "Chambre disponible immediatement a {$quarter}. Eau courante, compteur electrique individuel. Quartier accessible.",
            ]),
            'chambre meublee' => $this->pick([
                "Chambre meublee tout confort a {$quarter}. Lit double, armoire, table, climatisation. Eau chaude disponible. WiFi inclus.",
                "Magnifique chambre meublee VIP a {$quarter}. Literie neuve, decoration moderne, douche privative avec eau chaude.",
                "Chambre meublee standing a {$quarter}. Equipee d'un lit king size, climatisation split, frigo, TV ecran plat.",
                "Chambre meublee et climatisee a {$quarter}. Propre, moderne, tout equipe. Disponible immediatement.",
            ]),
            'studio simple' => $this->pick([
                "Joli studio de {$surface}m2 a {$quarter}. Cuisine integree, salle d'eau moderne, bon eclairage naturel. Quartier calme.",
                "Studio bien entretenu a {$quarter}. Sejour + coin cuisine + douche moderne. Carrelage complet, peinture fraiche.",
                "Studio moderne de {$surface}m2 situe a {$quarter}. Finitions soignees, espace optimise, lumineux. Parking disponible.",
                "Studio neuf a louer a {$quarter}. Entierement carrele, {$surface}m2, cuisine amenagee. Compteurs individuels.",
            ]),
            'studio meuble' => $this->pick([
                "Studio meuble VIP de {$surface}m2 a {$quarter}. Entierement equipe : lit, canape, TV, climatisation, cuisine.",
                "Magnifique studio meuble a {$quarter}. Decoration moderne, electromenager complet, connexion WiFi.",
                "Studio meuble grand standing a {$quarter}. {$surface}m2, climatise, cuisine equipee. Linge de maison fourni.",
                "Studio tout equipe a {$quarter}, {$surface}m2. Mobilier neuf, TV ecran plat, machine a laver.",
            ]),
            'appartement simple', 'appartement meuble' => $this->pick([
                "Bel appartement de {$bedrooms} chambres ({$surface}m2) a {$quarter}. Grand salon lumineux, cuisine equipee. Securite 24h/24.",
                "Appartement standing de {$bedrooms} pieces a {$quarter}. Finitions haut de gamme, carrelage importe. Parking privatif.",
                "Superbe appartement {$bedrooms} chambres a {$quarter}, {$surface}m2 habitables. Salon spacieux, cuisine moderne.",
                "Appartement neuf de {$bedrooms} chambres a {$quarter}. Construction recente, normes modernes. Environnement calme.",
                "Magnifique appartement {$bedrooms}P a {$quarter}. Grandes chambres avec placards, salon double, cuisine americaine.",
            ]),
            'maison' => $this->pick([
                "Magnifique villa de {$bedrooms} chambres sur {$surface}m2 a {$quarter}. Salon double, salle a manger, cuisine moderne. Jardin arbore, garage. Titre foncier.",
                "Villa duplex de standing a {$quarter}. RDC : salon, salle a manger, cuisine. Etage : {$bedrooms} chambres avec placards integres. Piscine.",
                "Belle villa de {$bedrooms} chambres a {$quarter}. Terrain de {$surface}m2 entierement cloture. Forage, groupe electrogene.",
                "Villa moderne {$bedrooms} chambres a {$quarter}. Architecture contemporaine, grandes baies vitrees, finitions luxueuses.",
                "Superbe villa a {$quarter}. {$bedrooms} chambres climatisees, {$bedrooms} salles de bain. Terrain de {$surface}m2. Titre foncier.",
            ]),
            'terrain' => $this->pick([
                "Terrain de {$surface}m2 a vendre a {$quarter}. Titre foncier disponible. Relief plat, facile a construire. Bordure route bitumee.",
                "Parcelle de {$surface}m2 a {$quarter}. Zone residentielle en plein developpement. Ideal pour investissement.",
                "Terrain constructible de {$surface}m2 a {$quarter}. Terrain plat et sec, aucun litige. Prix negociable.",
                "Lot de terrain de {$surface}m2 a {$quarter}. Titre foncier obtenu, bornage effectue. Ideal pour villa.",
                "Belle parcelle de {$surface}m2 a {$quarter}. Terrain viabilise (eau, electricite). Route d'acces praticable.",
            ]),
            default => "Propriete disponible a {$quarter}. Contactez-nous pour plus d'informations.",
        };
    }

    private function bedroomsForType(string $type): int
    {
        return match ($type) {
            'chambre simple', 'chambre meublee' => 1,
            'studio simple', 'studio meuble' => mt_rand(1, 2),
            'appartement simple', 'appartement meuble' => mt_rand(2, 4),
            'maison' => mt_rand(3, 6),
            'terrain' => 0,
            default => mt_rand(1, 3),
        };
    }

    private function surfaceForType(string $type): int
    {
        return match (true) {
            str_contains($type, 'chambre') => mt_rand(12, 25),
            str_contains($type, 'studio') => mt_rand(20, 45),
            str_contains($type, 'appartement') => mt_rand(50, 180),
            $type === 'maison' => mt_rand(100, 500),
            $type === 'terrain' => mt_rand(300, 2000),
            default => mt_rand(20, 100),
        };
    }

    /** @param string[] $options */
    private function pick(array $options): string
    {
        return $options[array_rand($options)];
    }

    private function cleanupImagePool(): void
    {
        if (File::isDirectory($this->imageDir)) {
            File::deleteDirectory($this->imageDir);
            $this->command->info('Image pool cleaned up');
        }
    }
}

