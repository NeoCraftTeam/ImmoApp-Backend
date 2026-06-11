<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HealthCheckService;
use Illuminate\Console\Command;

/**
 * Artisan command for health checking — used in CI/CD smoke tests
 * and monitoring scripts.
 *
 * Usage:
 *   php artisan app:health-check          # uses 30-second cache
 *   php artisan app:health-check --force  # bypasses cache
 *
 * Exit codes: 0 = healthy, 1 = degraded or unhealthy.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check {--force : Bypass the result cache and re-run all checks}';

    protected $description = 'Run comprehensive system health checks (DB, Redis, Queue, Storage, Meilisearch)';

    public function __construct(private readonly HealthCheckService $health)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Running health checks...');
        $this->newLine();

        $result = $this->health->run(force: (bool) $this->option('force'));

        foreach ($result['checks'] as $name => $check) {
            $icon = match ($check['status']) {
                'healthy' => '✅',
                'degraded' => '⚠️ ',
                default => '❌',
            };
            $latency = isset($check['latency_ms']) ? " ({$check['latency_ms']} ms)" : '';
            $label = ucfirst((string) $name);
            $message = $check['message'] ?? $check['status'];

            $line = "  {$icon} {$label}: {$message}{$latency}";

            match ($check['status']) {
                'healthy' => $this->line($line),
                'degraded' => $this->warn($line),
                default => $this->error($line),
            };

            if (isset($check['details'])) {
                foreach ($check['details'] as $key => $value) {
                    $this->line("       <fg=gray>↳ {$key}: {$value}</>");
                }
            }
        }

        $this->newLine();

        $overall = $result['status'];

        match ($overall) {
            'healthy' => $this->info("Overall: {$overall} ✅"),
            'degraded' => $this->warn("Overall: {$overall} ⚠️"),
            default => $this->error("Overall: {$overall} ❌"),
        };

        return $overall === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
