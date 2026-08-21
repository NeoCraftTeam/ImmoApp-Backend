<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

final class ImportOsmExtract extends Command
{
    protected $signature = 'geo:import-osm
                            {region=cameroon : Clé définie dans config/osm.php}
                            {--force : Autoriser la reconstruction du schéma osm_import}';

    protected $description = 'Importe un extrait OSM dans le schéma temporaire osm_import';

    public function handle(): int
    {
        $key = (string) $this->argument('region');
        $region = config("osm.regions.{$key}");
        if (!is_array($region)) {
            $this->error("Région OSM inconnue : {$key}");

            return self::FAILURE;
        }

        if (app()->isProduction() && !$this->option('force')) {
            $this->error('Utilisez --force pour importer en production.');

            return self::FAILURE;
        }

        $path = (string) config('osm.storage_directory').DIRECTORY_SEPARATOR.basename((string) $region['url']);
        if (!is_file($path)) {
            $this->error("Fichier absent. Lancez : php artisan geo:download-osm {$key}");

            return self::FAILURE;
        }

        DB::statement('DROP SCHEMA IF EXISTS osm_import CASCADE');
        DB::statement('CREATE SCHEMA osm_import');

        /** @var array<string, mixed> $connection */
        $connection = config('database.connections.'.config('database.default'));
        $host = self::resolveDatabaseHost($connection);
        $arguments = [
            (string) config('osm.binary'),
            '--create',
            '--slim',
            '--drop',
            '--output=flex',
            '--style='.(string) config('osm.style'),
            '--database='.(string) $connection['database'],
            '--host='.$host,
            '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'],
            '--number-processes=4',
            $path,
        ];

        $process = new Process($arguments, base_path(), [
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(null);
        $this->info("Import osm2pgsql de {$region['name']}…");
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (!$process->isSuccessful()) {
            $this->error('Échec de l’import osm2pgsql.');

            return self::FAILURE;
        }

        DB::statement('ANALYZE osm_import.places');
        DB::statement('ANALYZE osm_import.admin_boundaries');
        $this->info('Import brut terminé. Lancez geo:sync-osm.');

        return self::SUCCESS;
    }

    /**
     * Résout l'hôte de la base pour osm2pgsql.
     *
     * Supporte le split read/write pgsql (aucune clé `host` à plat, seulement
     * `write.host[]`/`read.host[]`), un host fourni sous forme de tableau, et
     * le fallback `DB_URL`. Retombe sur 127.0.0.1 quand rien ne se résout.
     *
     * @param  array<string, mixed>  $connection
     */
    public static function resolveDatabaseHost(array $connection): string
    {
        $host = $connection['host'] ?? $connection['write']['host'][0] ?? $connection['read']['host'][0] ?? null;

        if (is_array($host)) {
            $host = $host[0] ?? null;
        }

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $url = $connection['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $parsed = parse_url($url, PHP_URL_HOST);
            if (is_string($parsed) && $parsed !== '') {
                return $parsed;
            }
        }

        return '127.0.0.1';
    }
}
