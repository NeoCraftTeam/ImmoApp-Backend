<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geo\OsmPlaceSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class SyncOsmPlaces extends Command
{
    protected $signature = 'geo:sync-osm
                            {region=cameroon : Clé définie dans config/osm.php}';

    protected $description = 'Synchronise les lieux OSM importés vers les villes et quartiers KeyHome';

    public function handle(OsmPlaceSynchronizer $synchronizer): int
    {
        if (!Schema::hasTable('osm_import.places')) {
            $this->error('Aucun import brut trouvé. Lancez geo:import-osm.');

            return self::FAILURE;
        }

        $region = config('osm.regions.'.(string) $this->argument('region'));
        if (!is_array($region)) {
            $this->error('Région OSM inconnue.');

            return self::FAILURE;
        }

        $result = $synchronizer->sync($region['country_code'] ?? null);
        $this->table(['Villes', 'Quartiers', 'Quartiers non rattachés'], [[
            $result['cities'],
            $result['quarters'],
            $result['unmatched_quarters'],
        ]]);

        return self::SUCCESS;
    }
}
