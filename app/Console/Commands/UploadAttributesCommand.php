<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PropertyAttributeImportService;
use Illuminate\Console\Command;

final class UploadAttributesCommand extends Command
{
    protected $signature = 'make:upload-attributes {--fresh : Supprime et recrée les catégories/attributs}';

    protected $description = 'Importe les catégories et attributs de biens par défaut.';

    public function __construct(private readonly PropertyAttributeImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->importService->import((bool) $this->option('fresh'));

        $this->components->info(sprintf(
            '%d catégories et %d attributs importés avec succès.',
            $result['categories'],
            $result['attributes'],
        ));

        return self::SUCCESS;
    }
}
