<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\SurveySeeder;
use Illuminate\Console\Command;

final class InstallDefaultSurvey extends Command
{
    protected $signature = 'survey:install-default';

    protected $description = 'Insère de façon idempotente le sondage KeyHome et ses questions par défaut';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => SurveySeeder::class,
            '--force' => true,
        ]);

        $this->components->success('Template de sondage vérifié et installé.');

        return self::SUCCESS;
    }
}
