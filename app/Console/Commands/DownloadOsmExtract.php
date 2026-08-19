<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

final class DownloadOsmExtract extends Command
{
    protected $signature = 'geo:download-osm
                            {region=cameroon : Clé définie dans config/osm.php}
                            {--force : Remplacer un fichier déjà téléchargé}';

    protected $description = 'Télécharge et vérifie un extrait OpenStreetMap Geofabrik';

    public function handle(): int
    {
        $key = (string) $this->argument('region');
        $region = config("osm.regions.{$key}");

        if (!is_array($region)) {
            $this->error("Région OSM inconnue : {$key}");

            return self::FAILURE;
        }

        $directory = (string) config('osm.storage_directory');
        $filename = basename((string) $region['url']);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $temporaryPath = $path.'.part';

        if (is_file($path) && !$this->option('force')) {
            $this->info("Déjà téléchargé : {$path}");

            return self::SUCCESS;
        }

        Storage::build(['driver' => 'local', 'root' => $directory])->makeDirectory('');
        $this->info("Téléchargement de {$region['name']}…");

        @unlink($temporaryPath);
        $download = new Process([
            'curl',
            '--fail',
            '--location',
            '--retry', '3',
            '--retry-delay', '2',
            '--connect-timeout', '30',
            '--output', $temporaryPath,
            (string) $region['url'],
        ], base_path());
        $download->setTimeout(null);
        $download->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (!$download->isSuccessful() || !is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            @unlink($temporaryPath);
            $this->error('Téléchargement OSM incomplet.');

            return self::FAILURE;
        }

        $checksumResponse = Http::timeout(30)->retry(3, 1_000)->get((string) $region['checksum_url']);
        if (!$checksumResponse->successful()) {
            @unlink($temporaryPath);
            $this->error('Impossible de récupérer la somme de contrôle Geofabrik.');

            return self::FAILURE;
        }

        $expected = strtolower(preg_split('/\s+/', trim($checksumResponse->body()))[0]);
        $actual = md5_file($temporaryPath);
        if ($actual === false || !hash_equals($expected, strtolower($actual))) {
            @unlink($temporaryPath);
            $this->error('Le fichier téléchargé est corrompu (MD5 différent).');

            return self::FAILURE;
        }

        rename($temporaryPath, $path);
        $this->info(sprintf('Extrait vérifié : %s (%0.1f Mo)', $path, filesize($path) / 1_048_576));

        return self::SUCCESS;
    }
}
