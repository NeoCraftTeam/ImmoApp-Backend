<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SyncMeilisearchSettings extends Command
{
    protected $signature = 'meilisearch:sync-settings';

    protected $description = 'Synchronize Meilisearch filterable and sortable attributes';

    public function handle(): int
    {
        try {

            $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
            $index = $client->index('ad');

            $this->info('🔧 Mise à jour des attributs filtrables...');
            $index->updateFilterableAttributes([
                'status', 'is_visible', 'city', 'type', 'type_id', 'quarter_id',
                'bedrooms', 'bathrooms', 'price', 'surface_area',
                'has_parking', 'has_3d_tour', 'is_verified', 'is_boosted',
                '_geo', 'attributes',
            ]);

            $this->info('📊 Mise à jour des attributs triables...');
            $index->updateSortableAttributes([
                'price', 'surface_area', 'created_at', 'boost_score', 'reviews_avg_rating', 'views_count',
            ]);

            $this->info('✅ Configuration Meilisearch synchronisée avec succès !');

            return CommandAlias::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la synchronisation des paramètres Meilisearch : '.$e->getMessage());

            return CommandAlias::FAILURE;
        }
    }
}
