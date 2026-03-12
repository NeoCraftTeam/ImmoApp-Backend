<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\PropertyAttributeImportService;
use Illuminate\Database\Seeder;

class PropertyAttributeSeeder extends Seeder
{
    /**
     * Seed default property attributes.
     */
    public function run(): void
    {
        app(PropertyAttributeImportService::class)->import();
    }
}
