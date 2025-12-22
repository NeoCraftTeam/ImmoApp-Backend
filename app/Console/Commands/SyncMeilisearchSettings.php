<?php

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
                'city', 'bedrooms', 'type', 'price', 'quarter_id', 'type_id', 'status', 'created_at',
            ]);

            $this->info('📊 Mise à jour des attributs triables...');
            $index->updateSortableAttributes([
                'price', 'surface_area', 'type', 'bedrooms', 'city', 'created_at',
            ]);

            $this->info('✅ Configuration Meilisearch synchronisée avec succès !');

            return CommandAlias::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la synchronisation des paramètres Meilisearch : '.$e->getMessage());

            return CommandAlias::FAILURE;
        }
    }
}
