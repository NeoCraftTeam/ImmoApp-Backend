<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class RefreshOsmPlaces extends Command
{
    protected $signature = 'geo:refresh-osm
                            {regions?* : Une ou plusieurs clés définies dans config/osm.php}
                            {--list : Afficher les régions disponibles sans lancer d’import}
                            {--force-download : Télécharger à nouveau le fichier PBF}
                            {--cleanup : Supprimer chaque fichier PBF après une synchronisation réussie}
                            {--force : Autoriser la reconstruction du schéma osm_import en production}';

    protected $description = 'Télécharge, importe et synchronise un extrait OpenStreetMap';

    public function handle(): int
    {
        /** @var array<string, array{name:string,country_code:?string,url:string,checksum_url:string}> $available */
        $available = config('osm.regions', []);

        if ($this->option('list')) {
            $this->table(
                ['Clé', 'Nom', 'Code pays', 'Extrait Geofabrik'],
                collect($available)
                    ->map(fn (array $region, string $key): array => [
                        $key,
                        $region['name'],
                        $region['country_code'] ?? 'multi-pays',
                        basename($region['url']),
                    ])
                    ->values()
                    ->all(),
            );

            return self::SUCCESS;
        }

        /** @var list<string> $regions */
        $regions = $this->argument('regions');
        $regions = $regions === [] ? ['cameroon'] : $regions;

        foreach ($regions as $region) {
            if (!isset($available[$region])) {
                $this->error("Région OSM inconnue : {$region}");
                $this->line('Utilisez php artisan geo:refresh-osm --list pour afficher les clés disponibles.');

                return self::FAILURE;
            }
        }

        foreach ($regions as $index => $region) {
            if (count($regions) > 1) {
                $this->newLine();
                $this->components->twoColumnDetail(
                    sprintf('Pays %d/%d', $index + 1, count($regions)),
                    $available[$region]['name'],
                );
            }

            if ($this->refresh($region) !== self::SUCCESS) {
                return self::FAILURE;
            }

            if ($this->option('cleanup')) {
                $this->deleteExtract($region, $available[$region]['url']);
            }
        }

        return self::SUCCESS;
    }

    private function refresh(string $region): int
    {

        $this->newLine();
        $this->components->info('1/3 — Téléchargement et vérification');
        $downloadOptions = ['region' => $region];
        if ($this->option('force-download')) {
            $downloadOptions['--force'] = true;
        }

        if ($this->call('geo:download-osm', $downloadOptions) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('2/3 — Import PostGIS avec osm2pgsql');
        $importOptions = ['region' => $region];
        if ($this->option('force')) {
            $importOptions['--force'] = true;
        }

        if ($this->call('geo:import-osm', $importOptions) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('3/3 — Synchronisation KeyHome');
        if ($this->call('geo:sync-osm', ['region' => $region]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->success("Référentiel géographique « {$region} » actualisé.");

        return self::SUCCESS;
    }

    private function deleteExtract(string $region, string $url): void
    {
        $path = (string) config('osm.storage_directory').DIRECTORY_SEPARATOR.basename($url);
        if (!is_file($path)) {
            return;
        }

        if (@unlink($path)) {
            $this->components->info("Fichier téléchargé supprimé après synchronisation : {$region}");
        } else {
            $this->components->warn("Impossible de supprimer le fichier téléchargé : {$path}");
        }
    }
}
