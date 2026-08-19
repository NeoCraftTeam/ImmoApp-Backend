<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PropertyAttributeImportService;
use App\Support\PropertyAttributeCatalog;
use Illuminate\Console\Command;

final class SyncPropertyAttributeCatalog extends Command
{
    protected $signature = 'catalog:sync-attributes
                            {--fresh : Remplacer intégralement le catalogue existant}
                            {--dry-run : Afficher les volumes sans modifier la base}';

    protected $description = 'Insère ou actualise le catalogue professionnel des attributs immobiliers';

    public function handle(PropertyAttributeImportService $importer): int
    {
        $categories = PropertyAttributeCatalog::categories();
        $attributeCount = array_sum(array_map(
            static fn (array $category): int => count($category['attributes']),
            $categories,
        ));

        if ($this->option('dry-run')) {
            $this->table(['Catégories', 'Attributs', 'Mode'], [[
                count($categories),
                $attributeCount,
                $this->option('fresh') ? 'remplacement' : 'synchronisation',
            ]]);

            return self::SUCCESS;
        }

        $result = $importer->import((bool) $this->option('fresh'));
        $this->components->success(sprintf(
            'Catalogue synchronisé : %d catégories, %d attributs.',
            $result['categories'],
            $result['attributes'],
        ));

        return self::SUCCESS;
    }
}
