<?php

declare(strict_types=1);

return [
    'binary' => env('OSM2PGSQL_BINARY', 'osm2pgsql'),
    'storage_directory' => storage_path('app/private/osm'),
    'style' => base_path('resources/osm/keyhome-places.lua'),
    'regions' => [
        'cameroon' => [
            'name' => 'Cameroun',
            'country_code' => 'CM',
            'url' => 'https://download.geofabrik.de/africa/cameroon-latest.osm.pbf',
            'checksum_url' => 'https://download.geofabrik.de/africa/cameroon-latest.osm.pbf.md5',
        ],
        'france' => [
            'name' => 'France',
            'country_code' => 'FR',
            'url' => 'https://download.geofabrik.de/europe/france-latest.osm.pbf',
            'checksum_url' => 'https://download.geofabrik.de/europe/france-latest.osm.pbf.md5',
        ],
        'germany' => [
            'name' => 'Allemagne',
            'country_code' => 'DE',
            'url' => 'https://download.geofabrik.de/europe/germany-latest.osm.pbf',
            'checksum_url' => 'https://download.geofabrik.de/europe/germany-latest.osm.pbf.md5',
        ],
        'germany-bremen' => [
            'name' => 'Allemagne — Land de Brême (pilote léger)',
            'country_code' => 'DE',
            'url' => 'https://download.geofabrik.de/europe/germany/bremen-latest.osm.pbf',
            'checksum_url' => 'https://download.geofabrik.de/europe/germany/bremen-latest.osm.pbf.md5',
        ],
        'africa' => [
            'name' => 'Afrique',
            'country_code' => null,
            'url' => 'https://download.geofabrik.de/africa-latest.osm.pbf',
            'checksum_url' => 'https://download.geofabrik.de/africa-latest.osm.pbf.md5',
        ],
    ],
    'city_place_types' => ['city', 'town', 'municipality', 'village'],
    'quarter_place_types' => ['suburb', 'quarter', 'neighbourhood', 'locality'],
    'quarter_max_city_distance_km' => 75,
];
