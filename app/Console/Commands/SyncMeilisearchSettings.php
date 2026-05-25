<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SyncMeilisearchSettings extends Command
{
    protected $signature = 'meilisearch:sync-settings {--skip-embedder : Skip embedder update (faster for attribute-only syncs)}';

    protected $description = 'Synchronize Meilisearch filterable and sortable attributes';

    public function handle(): int
    {
        try {

            $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
            $index = $client->index('ad');

            $this->info('🔧 Mise à jour des attributs filtrables...');
            $index->updateFilterableAttributes([
                'status', 'is_visible', 'city', 'type', 'type_id', 'quarter_id',
                'city_id', 'transaction_type',
                'bedrooms', 'bathrooms', 'price', 'surface_area',
                'has_parking', 'has_3d_tour', 'is_verified', 'is_boosted',
                'relevance_score',
                '_geo', 'attributes',
            ]);

            $this->info('📊 Mise à jour des attributs triables...');
            $index->updateSortableAttributes([
                'price', 'surface_area', 'created_at', 'boost_score',
                'reviews_avg_rating', 'views_count', 'relevance_score',
            ]);

            $this->info('🏆 Mise à jour du ranking personnalisé...');
            $index->updateRankingRules([
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
                'desc(relevance_score)',
            ]);

            $this->info('🔤 Mise à jour des synonymes FR-CM...');
            $index->updateSynonyms($this->buildSynonyms());

            $this->info('🛑 Mise à jour des stop words FR...');
            $index->updateStopWords([
                'le', 'la', 'les', 'l', 'de', 'du', 'des', 'un', 'une',
                'et', 'en', 'à', 'au', 'aux', 'sur', 'par', 'pour', 'avec',
                'dans', 'qui', 'que', 'je', 'est', 'pas', 'plus', 'très',
            ]);

            // ── 3.1 Embedder multilingue (Cohere embed-multilingual-v3.0) ────────
            // Only configured when COHERE_API_KEY is set in .env.
            // Use --skip-embedder to avoid touching this during routine re-syncs.
            if (!$this->option('skip-embedder')) {
                $cohereKey = (string) config('services.cohere.api_key', '');

                if ($cohereKey !== '') {
                    $this->info('🤖 Configuration de l\'embedder multilingue (Cohere)...');

                    $index->updateEmbedders([
                        'default' => [
                            'source' => 'rest',
                            'url' => 'https://api.cohere.ai/v1/embed',
                            'apiKey' => $cohereKey,
                            'dimensions' => 1024,
                            'inputType' => 'search_document',
                            'documentTemplate' => 'Annonce à {{doc.quarter_name}}, {{doc.city_name}} : {{doc.title}}. {{doc.description}} Type: {{doc.ad_type_name}}. Prix: {{doc.price}} FCFA. Transaction: {{doc.transaction_type}}.',
                            'request' => [
                                'texts' => ['{{text}}'],
                                'model' => 'embed-multilingual-v3.0',
                                'input_type' => 'search_document',
                            ],
                            'response' => [
                                'embeddings' => ['{{embeddings[0]}}'],
                            ],
                        ],
                    ]);
                } else {
                    $this->line('ℹ️  COHERE_API_KEY non défini — embedder ignoré. Définissez-le pour activer la recherche hybride.');
                }
            }

            $this->info('✅ Configuration Meilisearch synchronisée avec succès !');

            return CommandAlias::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la synchronisation des paramètres Meilisearch : '.$e->getMessage());

            return CommandAlias::FAILURE;
        }
    }

    /**
     * Expand synonym groups into Meilisearch bidirectional format.
     *
     * Each group is a list of equivalent words; every word maps to all others.
     *
     * @return array<string, string[]>
     */
    private function buildSynonyms(): array
    {
        $groups = [
            // Property types
            ['studio', 'garçonnière', 'chambre garçonnière'],
            ['appartement', 'appart', 'flat', 'logement'],
            ['villa', 'maison', 'pavillon', 'bungalow', 'maison individuelle'],
            ['terrain', 'parcelle', 'lot', 'foncier'],
            ['bureau', 'local commercial', 'commerce', 'boutique'],
            ['duplex', 'maisonette'],
            // Amenities
            ['parking', 'garage', 'stationnement', 'place de parking'],
            ['meublé', 'meuble', 'équipé', 'avec meubles'],
            ['climatisation', 'clim', 'climatiseur'],
            ['wc', 'toilettes', 'sanitaires'],
            ['gardien', 'vigile', 'sécurité', 'concierge'],
            ['ascenseur', 'elevator'],
            ['balcon', 'terrasse', 'véranda'],
            ['piscine', 'pool'],
            ['clôture', 'enceinte', 'mur de clôture'],
            // Transaction types
            ['location', 'louer', 'loue', 'en location', 'à louer'],
            ['vente', 'à vendre', 'achat', 'acheter'],
            // Quarters / cities (alternate spellings)
            ['biyem-assi', 'biyem assi', 'biyemassi'],
            ['omnisport', 'stade omnisport'],
            ['akwa', 'akwa nord'],
            ['bonapriso', 'bonamoussadi'],
            ['bastos', 'quartier bastos'],
            ['douala', 'dla'],
            ['yaoundé', 'yaounde', 'yde'],
            ['abidjan', 'abidjan city'],
            // Rooms / features
            ['chambre', 'pièce', 'room'],
            ['salle de bain', 'douche', 'sdb', 'salle d\'eau'],
            ['cuisine', 'kitchenette'],
            ['salon', 'séjour', 'living'],
        ];

        $synonyms = [];
        foreach ($groups as $group) {
            foreach ($group as $word) {
                $synonyms[$word] = array_values(array_filter($group, fn (string $w): bool => $w !== $word));
            }
        }

        return $synonyms;
    }
}
