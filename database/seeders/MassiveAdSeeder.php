<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\PropertyAttribute;
use App\Models\Quarter;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MassiveAdSeeder extends Seeder
{
    private const TOTAL_ADS = 2000;

    private const IMAGES_PER_AD_MIN = 7;

    private const IMAGES_PER_AD_MAX = 10;

    private const SEED_FAST_MODE_DEFAULT = true;

    /**
     * Maps normalized ad type to folder name in resources/seeder-images/
     * Download 10-20 images from Unsplash per category and place in these folders.
     */
    private const TYPE_TO_FOLDER = [
        'chambre simple' => 'chambre',
        'chambre meublee' => 'chambre',
        'studio simple' => 'studio',
        'studio meuble' => 'studio',
        'appartement simple' => 'appartement',
        'appartement meuble' => 'appartement',
        'maison' => 'maison',
        'terrain' => 'terrain',
        'commercial' => 'commercial',
        'commerces' => 'commercial',
    ];

    private string $imageBaseDir;

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
    private array $customerIds = [];

    /** @var string[] */
    private array $attributeSlugs = [];

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
        'commercial' => [500000, 50000000],
        'commerces' => [500000, 50000000],
    ];

    public function run(): void
    {
        $this->imageBaseDir = resource_path('seeder-images');
        $fastMode = config('seeding.massive_ad_fast_mode', self::SEED_FAST_MODE_DEFAULT);
        $totalAds = $fastMode ? 200 : self::TOTAL_ADS;
        $this->command->info('Seeding '.$totalAds.' realistic ads with images...'.($fastMode ? ' (fast mode)' : ''));

        $this->ensureImageFoldersExist();
        $hasImages = !empty($this->getImagesForType('maison'));
        if (!$hasImages) {
            $this->command->warn('No seeder images found. Seeding ads without images.');
            $this->command->line('  To add images: place 10-20 jpg/png/webp per category in:');
            $this->command->line('  '.$this->imageBaseDir.'/{maison,terrain,chambre,studio,appartement,commercial}/');
        }

        $this->createUsers();
        $this->loadReferenceData();

        $this->command->info('Disabling media conversions for faster seeding...');
        app()->bind(FileManipulator::class, fn () => new class extends FileManipulator
        {
            public function createDerivedFiles(
                Media $media,
                array $onlyConversionNames = [],
                bool $onlyMissing = false,
                bool $withResponsiveImages = true,
                bool $queueAll = false,
            ): void {
                // Skip conversions during seeding
            }
        });

        $this->createAds($totalAds);

        app()->forgetInstance(FileManipulator::class);

        $this->command->info('Seeding complete! '.Ad::count().' ads in database.');
        $this->command->warn('Run "php artisan media-library:regenerate" to generate image conversions.');
    }

    private function createUsers(): void
    {
        $this->command->info('Creating users...');
        $password = Hash::make('password');
        $cities = City::all();

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

        $customers = User::factory()->count(50)->customers()->recycle($cities)->create(['password' => $password]);
        $this->customerIds = $customers->pluck('id')->toArray();
        $this->command->info('  40 agents (20 agences), 50 clients');
    }

    private function loadReferenceData(): void
    {
        $coordsPath = storage_path('app/quarter_coordinates.json');
        if (file_exists($coordsPath)) {
            $this->quarterCoords = json_decode(file_get_contents($coordsPath), true) ?? [];
        }

        $quarters = Quarter::with('city')->get();
        $this->quarterIds = $quarters->pluck('id')->toArray();
        $this->quarterNames = $quarters->mapWithKeys(
            fn (Quarter $q) => [$q->id => $q->name.', '.$q->city->name]
        )->toArray();

        $this->agentIds = User::where('role', UserRole::AGENT)->pluck('id')->toArray();
        $this->typeMap = AdType::pluck('id', 'name')->toArray();

        if (empty($this->agencyMap)) {
            foreach (Agency::all() as $agency) {
                $this->agencyMap[$agency->owner_id] = $agency->id;
            }
        }

        if (empty($this->customerIds)) {
            $this->customerIds = User::where('role', UserRole::CUSTOMER)->pluck('id')->toArray();
        }

        $this->attributeSlugs = PropertyAttribute::query()->active()->pluck('slug')->toArray();

        $this->command->info('  '.count($this->quarterIds).' quarters, '.count($this->agentIds).' agents, '.count($this->typeMap).' types, '.count($this->attributeSlugs).' attributes');
    }

    private function ensureImageFoldersExist(): void
    {
        $folders = array_unique(array_values(self::TYPE_TO_FOLDER));
        foreach ($folders as $folder) {
            File::ensureDirectoryExists($this->imageBaseDir.'/'.$folder);
        }

        $total = 0;
        $counts = [];
        foreach ($folders as $folder) {
            $n = count($this->globImages($this->imageBaseDir.'/'.$folder));
            $counts[$folder] = $n;
            $total += $n;
        }

        if ($total > 0) {
            $details = collect($counts)->map(fn (int $n, string $f) => "{$f}:{$n}")->implode(', ');
            $this->command->info("  {$total} images (".$details.')');
        }
    }

    private function createAds(int $totalAds = self::TOTAL_ADS): void
    {
        $fastMode = config('seeding.massive_ad_fast_mode', self::SEED_FAST_MODE_DEFAULT);
        $imgMin = self::IMAGES_PER_AD_MIN;
        $imgMax = self::IMAGES_PER_AD_MAX;
        $this->command->info("Creating {$totalAds} ads with {$imgMin}-{$imgMax} images each...");

        $typeNames = array_keys($this->typeMap);
        if (empty($typeNames)) {
            $this->command->error('No ad types found. Run AdTypeSeeder first.');

            return;
        }
        if (empty($this->quarterIds)) {
            $this->command->error('No quarters found. Run CameroonCitiesSeeder first.');

            return;
        }
        if (empty($this->agentIds)) {
            $this->command->error('No agents found. Check createUsers().');

            return;
        }

        $progress = $this->command->getOutput()->createProgressBar($totalAds);
        $progress->start();

        $perType = (int) ceil($totalAds / count($typeNames));
        $created = 0;
        $imageErrors = 0;

        Model::withoutEvents(function () use ($typeNames, $perType, $totalAds, $imgMin, $imgMax, &$created, &$imageErrors, $progress): void {
            Ad::withoutSyncingToSearch(function () use ($typeNames, $perType, $totalAds, $imgMin, $imgMax, &$created, &$imageErrors, $progress): void {
                DB::transaction(function () use ($typeNames, $perType, $totalAds, $imgMin, $imgMax, &$created, &$imageErrors, $progress): void {
                    foreach ($typeNames as $typeName) {
                        $count = min($perType, $totalAds - $created);
                        $normalizedType = $this->normalizeTypeName($typeName);
                        $imageFiles = $this->getImagesForType($normalizedType);

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

                            $attributes = $this->randomAttributes();

                            $price = mt_rand($priceRange[0], $priceRange[1]);
                            $isTerrain = in_array($normalizedType, ['terrain', 'commercial', 'commerces']);
                            $hasForfait = !$isTerrain && (bool) mt_rand(0, 1);
                            $hasDetailed = !$isTerrain && !$hasForfait;

                            $status = AdStatus::from($this->randomStatus());
                            $ad = Ad::forceCreate([
                                'id' => (string) Str::orderedUuid(),
                                'title' => $title,
                                'slug' => Ad::generateUniqueSlug($title),
                                'description' => $description,
                                'adresse' => $quarterLabel,
                                'price' => $price,
                                'surface_area' => $surface,
                                'bedrooms' => $bedrooms,
                                'bathrooms' => max(1, (int) round($bedrooms * 0.7)),
                                'has_parking' => in_array($normalizedType, ['maison', 'appartement meuble', 'appartement simple']),
                                'location' => "POINT({$lng} {$lat})",
                                'status' => $status,
                                'user_id' => $agentId,
                                'quarter_id' => $quarterId,
                                'type_id' => $this->typeMap[$typeName],
                                'agency_id' => $this->agencyMap[$agentId] ?? null,
                                'attributes' => $attributes,
                                'deposit_amount' => $isTerrain ? null : $this->depositForType($normalizedType, $price),
                                'minimum_lease_duration' => $isTerrain ? null : $this->leaseDurationForType(),
                                'charges_forfaitaires' => $hasForfait,
                                'charges_montant_forfait' => $hasForfait ? $this->chargesForfaitForType($normalizedType) : null,
                                'charges_eau' => $hasDetailed ? mt_rand(2, 8) * 1000 : null,
                                'charges_electricite' => $hasDetailed ? mt_rand(3, 15) * 1000 : null,
                                'charges_autres' => $hasDetailed ? $this->generateChargesAutres() : null,
                                'created_at' => now()->subDays($daysAgo),
                                'updated_at' => now()->subDays($daysAgo),
                            ]);

                            $imagesPerAd = mt_rand($imgMin, $imgMax);
                            $shuffled = $imageFiles;
                            shuffle($shuffled);
                            $toAdd = array_slice($shuffled, 0, $imagesPerAd);
                            foreach ($toAdd as $path) {
                                try {
                                    $ad->addMedia($path)
                                        ->preservingOriginal()
                                        ->toMediaCollection('images');
                                } catch (\Exception $e) {
                                    $imageErrors++;
                                }
                            }

                            $this->seedProximityData($ad, $normalizedType);

                            $this->createReviewsForAd($ad, $daysAgo);

                            $created++;
                            $progress->advance();
                        }
                    }
                });
            });
        });

        $progress->finish();
        $this->command->newLine();
        $msg = "  {$created} ads created";
        if ($imageErrors > 0) {
            $msg .= " ({$imageErrors} image errors)";
        }
        $this->command->info($msg);
    }

    /**
     * Glob images in directory (jpg, jpeg, png, webp). Portable: works without GLOB_BRACE (Alpine/musl).
     *
     * @return string[]
     */
    private function globImages(string $dir): array
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $files = [];
        foreach ($extensions as $ext) {
            $found = glob($dir.'/*.'.$ext);
            if ($found !== false) {
                $files = array_merge($files, $found);
            }
        }

        return $files;
    }

    /**
     * @return string[]
     */
    private function getImagesForType(string $normalizedType): array
    {
        $folder = self::TYPE_TO_FOLDER[$normalizedType] ?? 'maison';
        $dir = $this->imageBaseDir.'/'.$folder;
        $files = $this->globImages($dir);
        if (!empty($files)) {
            return $files;
        }
        foreach (array_unique(array_values(self::TYPE_TO_FOLDER)) as $fallback) {
            if ($fallback === $folder) {
                continue;
            }
            $fallbackFiles = $this->globImages($this->imageBaseDir.'/'.$fallback);
            if (!empty($fallbackFiles)) {
                return $fallbackFiles;
            }
        }

        return [];
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
            'commercial' => 'commercial',
            'commerces' => 'commercial',
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
        $ref = strtoupper(Str::random(3));

        return match ($type) {
            'chambre simple' => $this->pick([
                "Chambre Simple {$name} - {$short}",
                "Chambre Individuelle {$short} Ref.{$ref}",
                "Chambre Standard Residence {$name} {$short}",
                "Belle Chambre {$short} - {$name}",
                "Chambre Propre Cite {$name} {$short}",
            ]),
            'chambre meublee' => $this->pick([
                "Chambre Meublee VIP {$name} - {$short}",
                "Chambre Meublee Climatisee {$short} Ref.{$ref}",
                "Chambre Tout Confort Residence {$name} {$short}",
                "Chambre Standing {$name} - {$short}",
                "Chambre Equipee Cite {$name} {$short}",
            ]),
            'studio simple' => $this->pick([
                "Studio Neuf {$name} - {$short}",
                "Studio 1 Piece {$short} Ref.{$ref}",
                "Joli Studio Residence {$name} {$short}",
                "Studio Moderne {$name} - {$short}",
                "Studio {$short} Cite {$name}",
            ]),
            'studio meuble' => $this->pick([
                "Studio Meuble VIP {$name} - {$short}",
                "Studio Meuble Grand Standing {$short} Ref.{$ref}",
                "Studio Tout Equipe Residence {$name} {$short}",
                "Studio Meuble Climatise {$name} - {$short}",
                "Studio Luxe {$name} {$short}",
            ]),
            'appartement simple' => $this->pick([
                "Appartement {$bedrooms}Ch Residence {$name} - {$short}",
                "Bel Appartement {$bedrooms}P {$short} Ref.{$ref}",
                "Residence {$name} - Appart {$bedrooms}P {$short}",
                "Appartement Neuf {$bedrooms}P {$name} - {$short}",
                "Appartement Spacieux {$bedrooms}Ch Cite {$name} {$short}",
            ]),
            'appartement meuble' => $this->pick([
                "Residence {$name} - Appart Meuble {$bedrooms}P {$short}",
                "Appartement Meuble Standing {$bedrooms}Ch {$short} Ref.{$ref}",
                "Appart VIP Tout Meuble {$name} - {$short}",
                "Appartement Meuble Haut Standing {$bedrooms}P {$name}",
                "Residence {$name} - {$bedrooms}P Meublees {$short}",
            ]),
            'maison' => $this->pick([
                "Villa {$name} - {$short}",
                "Villa {$name} {$bedrooms}Ch - {$short}",
                "Villa Duplex {$bedrooms}Ch {$name} {$short}",
                "Belle Villa Moderne {$bedrooms}Ch {$short} Ref.{$ref}",
                "Villa Standing {$bedrooms} Chambres Cite {$name} {$short}",
                "Duplex de Luxe {$bedrooms}Ch {$name} - {$short}",
                "Residence {$name} - Villa {$bedrooms}Ch {$short}",
                "Maison {$bedrooms} Chambres {$name} - {$short}",
                "Villa {$name} avec Piscine - {$short}",
            ]),
            'terrain' => $this->pick([
                "Terrain {$surface}m2 {$short} Ref.{$ref}",
                "Parcelle Titree {$surface}m2 {$name} - {$short}",
                "Terrain Constructible {$surface}m2 {$short} - Lot {$ref}",
                "Terrain a Vendre {$surface}m2 Cite {$name} {$short}",
                "Lot {$surface}m2 {$short} - {$name}",
                "Terrain Plat {$surface}m2 {$short} Ref.{$ref}",
            ]),
            default => "Propriete {$name} - {$short}",
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

    /**
     * @return string[]
     */
    private function randomAttributes(): array
    {
        if (empty($this->attributeSlugs)) {
            return [];
        }

        $count = min(mt_rand(4, 8), count($this->attributeSlugs));
        $shuffled = $this->attributeSlugs;
        shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }

    private function seedProximityData(Ad $ad, string $type): void
    {
        // 70% of non-terrain/commercial ads get proximity data
        if (in_array($type, ['terrain', 'commercial', 'commerces'], true)) {
            return;
        }
        if (mt_rand(1, 10) > 7) {
            return;
        }

        $isUrban = in_array($type, ['chambre simple', 'chambre meublee', 'studio simple', 'studio meuble'], true);
        $isApartment = str_contains($type, 'appartement');

        $data = [];

        // Route principale — always present
        $data['distance_main_road_m'] = $isUrban
            ? mt_rand(20, 400)
            : ($isApartment ? mt_rand(50, 800) : mt_rand(100, 2000));

        // Commerces / marchés
        if (mt_rand(0, 1)) {
            $data['distance_shops_m'] = $isUrban
                ? mt_rand(50, 700)
                : ($isApartment ? mt_rand(100, 1500) : mt_rand(200, 3000));
        }

        // Transport en commun
        if (mt_rand(0, 1)) {
            $data['distance_transport_m'] = $isUrban
                ? mt_rand(30, 300)
                : ($isApartment ? mt_rand(50, 600) : mt_rand(100, 1200));
        }

        // École / Université
        if (mt_rand(0, 1)) {
            $data['distance_school_m'] = $isUrban
                ? mt_rand(100, 1000)
                : ($isApartment ? mt_rand(200, 2000) : mt_rand(300, 5000));
        }

        // Hôpital / Clinique
        if (mt_rand(0, 1)) {
            $data['distance_hospital_m'] = $isUrban
                ? mt_rand(300, 2000)
                : ($isApartment ? mt_rand(500, 5000) : mt_rand(1000, 10000));
        }

        $ad->updateQuietly($data);
    }

    private function createReviewsForAd(Ad $ad, int $daysAgo): void
    {
        if (empty($this->customerIds)) {
            return;
        }

        $reviewCount = mt_rand(3, 15);
        $maxPossible = min($reviewCount, count($this->customerIds));
        $reviewCount = $maxPossible;

        $usedCustomers = [];
        $comments = [
            'Logement excellent, exactement comme décrit dans l\'annonce. Propriétaire très sympathique et disponible. Je recommande vivement sans hésitation.',
            'Appartement propre, bien aménagé et très bien situé. Accès facile, quartier calme et sécurisé. L\'eau et l\'électricité sont disponibles 24h/24. Très satisfait.',
            'Villa magnifique avec tout le confort moderne. Le gardien est présent la nuit, le parking est spacieux. Je n\'ai rien à redire, c\'est parfait pour une famille.',
            'Chambre propre et bien entretenue dans une concession calme. Le propriétaire est sérieux, réactif et respectueux. Voisinage agréable. Je reviendrai.',
            'Studio meublé de qualité, mobilier neuf, climatisation fonctionnelle. Internet inclus. L\'annonce correspondait à 100% à la réalité. Très bonne expérience.',
            'Superbe appartement, finitions haut de gamme. Quartier résidentiel très bien desservi par les transports. Tout est conforme aux photos.',
            'Bonne adresse, proximité des commerces et du marché. Eau courante, électricité stable. Le propriétaire nous a aidés pour l\'installation. Très content.',
            'Logement idéal pour un professionnel. Calme, bien éclairé, cuisine bien équipée. La sécurité du quartier est rassurante. Je recommande à 100%.',
            'Maison spacieuse avec jardin, forage d\'eau privé et groupe électrogène. Idéale pour une grande famille. Le prix est juste par rapport au standing.',
            'Très bon logement dans l\'ensemble. Quelques petits détails à finir dans la salle de bain, mais rien de grave. Propriétaire de bonne volonté.',
            'Appartement conforme à l\'annonce. Bon rapport qualité-prix pour le quartier. Le seul bémol est le bruit de la route principale le matin.',
            'Bon séjour. Le logement est propre et fonctionnel. Propriétaire disponible sur WhatsApp pour tout problème. Je recommande.',
            'Chambre correcte et bien entretenue. Eau chaude disponible le matin. Voisinage calme. Légèrement loin du marché mais accessible en moto.',
            'Studio bien équipé, cuisine fonctionnelle, bonne ventilation naturelle. Quelques coupures d\'électricité occasionnelles mais c\'est la norme dans le quartier.',
            'Belle villa, bien construite. Le jardin nécessite un peu d\'entretien mais l\'ensemble est en bon état. Quartier résidentiel agréable.',
            'Logement dans une bonne résidence sécurisée. Gardiennage 24h/24. Parking couvert. L\'appartement est propre et les voisins sont respectueux.',
            'Appartement meublé de bon standing. Lit confortable, TV écran plat, WiFi rapide. Quelques équipements de cuisine manquants mais globalement satisfait.',
            'Logement correct sans plus. Le prix est légèrement élevé par rapport à ce qui est proposé. Quelques travaux de rénovation seraient bienvenus.',
            'Chambre propre mais un peu petite. La douche fonctionne bien, l\'électricité est stable. Quartier un peu bruyant la nuit.',
            'Studio acceptable pour le prix demandé. La plomberie a quelques problèmes que le propriétaire a promis de réparer.',
            'Appartement conforme à la description mais les photos le montraient plus lumineux. Correct pour une première installation.',
            'Maison spacieuse mais nécessitant quelques réparations (carrelage fissuré, peinture à refaire). Propriétaire de bonne volonté.',
            'Logement décevant par rapport aux photos. La cuisine n\'était pas équipée comme indiqué. Le propriétaire doit corriger son annonce.',
            'Des problèmes de plomberie récurrents. L\'eau chaude ne fonctionnait pas la moitié du temps. Propriétaire difficile à joindre.',
            'Quartier moins sécurisé que ce qui était annoncé. Quelques nuisances sonores la nuit. Le logement en lui-même est correct.',
            'Résidence bien entretenue, ascenseur fonctionnel, parking sécurisé. Appartement lumineux et spacieux. Charges raisonnables. Très satisfait.',
            'Propriétaire réactif et professionnel. Logement livré propre avec tout le nécessaire. Je n\'hésite pas à recommander cette adresse.',
            'Très bon rapport qualité-prix. Le quartier est en plein développement, tout est à portée de main. Électricité et eau jamais en panne.',
            'Séjour professionnel réussi grâce à ce logement bien équipé et bien placé. WiFi excellent, climatisation puissante. Reviendrai sans hésiter.',
            'Superbe vue depuis le balcon, appartement lumineux, finitions modernes. Le gardiennage 24h/24 est un vrai plus pour la sécurité.',
        ];

        for ($i = 0; $i < $reviewCount; $i++) {
            $customerId = $this->customerIds[array_rand($this->customerIds)];
            if (in_array($customerId, $usedCustomers, true)) {
                continue;
            }
            $usedCustomers[] = $customerId;

            $reviewDaysAgo = mt_rand(0, min($daysAgo, 90));
            $rating = (float) mt_rand(1, 5);
            if (mt_rand(0, 1) === 1 && $rating < 5) {
                $rating += 0.5;
            }

            Review::forceCreate([
                'id' => (string) Str::orderedUuid(),
                'ad_id' => $ad->id,
                'user_id' => $customerId,
                'rating' => min(5.0, $rating),
                'comment' => $this->pick($comments),
                'agency_id' => $ad->agency_id,
                'created_at' => now()->subDays($reviewDaysAgo),
                'updated_at' => now()->subDays($reviewDaysAgo),
            ]);
        }
    }

    private function depositForType(string $type, int $price): string
    {
        $months = match (true) {
            $type === 'maison'                          => mt_rand(1, 3),
            str_contains($type, 'appartement meuble')  => mt_rand(1, 2),
            str_contains($type, 'appartement')         => mt_rand(1, 2),
            default                                     => 1,
        };
        $amount = number_format($months * $price, 0, ',', ' ');

        return $months === 1
            ? "1 mois de caution ({$amount} FCFA)"
            : "{$months} mois de caution ({$amount} FCFA)";
    }

    private function leaseDurationForType(): string
    {
        return $this->pick([
            'Mensuel, sans engagement',
            '3 mois renouvelables',
            '6 mois renouvelables',
            '6 mois ferme',
            '1 an renouvelable',
            '1 an ferme',
            '2 ans renouvelables',
        ]);
    }

    private function chargesForfaitForType(string $type): int
    {
        return match (true) {
            $type === 'maison'                 => mt_rand(10, 35) * 1000,
            str_contains($type, 'appartement') => mt_rand(6, 22) * 1000,
            str_contains($type, 'studio')      => mt_rand(4, 14) * 1000,
            default                            => mt_rand(2, 8) * 1000,
        };
    }

    private function generateChargesAutres(): ?string
    {
        $pool = [
            'Gardiennage'               => [3000, 4000, 5000, 6000, 7000, 8000, 10000],
            'Enlèvement des ordures'    => [1000, 1500, 2000, 2500, 3000],
            'Entretien espaces communs' => [2000, 2500, 3000, 4000, 5000],
            'Groupe électrogène'        => [3000, 4000, 5000, 6000],
            'Gardien de nuit'           => [3000, 4000, 5000, 6000, 7000],
            'Nettoyage parties communes' => [1500, 2000, 2500, 3000],
        ];

        $items = [];
        foreach ($pool as $label => $amounts) {
            if (mt_rand(0, 2) === 0) {
                $amount    = $amounts[array_rand($amounts)];
                $formatted = number_format($amount, 0, ',', ' ');
                $items[]   = "{$label} : {$formatted} FCFA/mois";
            }
        }

        return empty($items) ? null : implode(', ', $items);
    }
}
