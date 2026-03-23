<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command for health checking — used in CI/CD smoke tests
 * and monitoring scripts.
 *
 * Usage: php artisan app:health-check
 * Exit code 0 = all checks pass, 1 = one or more checks failed.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Run comprehensive system health checks (DB, Redis, Queue, Storage)';

    public function handle(): int
    {
        $this->info('Running health checks...');
        $allPassed = true;

        // Database
        try {
            DB::select('SELECT 1');
            $this->line('  ✅ Database: connected');
        } catch (\Throwable $e) {
            $this->error("  ❌ Database: {$e->getMessage()}");
            $allPassed = false;
        }

        // Redis
        try {
            Cache::store('redis')->put('health_check_cli', true, 10);
            $this->line('  ✅ Redis: connected');
        } catch (\Throwable) {
            $this->warn('  ⚠️  Redis: unavailable (cache will fall back to database/file)');
        }

        // Queue tables
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->line("  ✅ Queue: {$pending} pending, {$failed} failed");

            if ($failed > 100) {
                $this->warn("  ⚠️  High number of failed jobs: {$failed}");
            }
        } catch (\Throwable $e) {
            $this->error("  ❌ Queue: {$e->getMessage()}");
            $allPassed = false;
        }

        // Storage
        $storagePath = storage_path();
        if (is_writable($storagePath)) {
            $freeMb = round(disk_free_space($storagePath) / 1024 / 1024, 2);
            $this->line("  ✅ Storage: writable, {$freeMb} MB free");

            if ($freeMb < 500) {
                $this->warn("  ⚠️  Low disk space: {$freeMb} MB remaining");
            }
        } else {
            $this->error('  ❌ Storage: not writable');
            $allPassed = false;
        }

        // Meilisearch
        $meiliHost = config('scout.meilisearch.host');
        if ($meiliHost) {
            try {
                $ch = curl_init("{$meiliHost}/health");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_CONNECTTIMEOUT => 2,
                ]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $this->line('  ✅ Meilisearch: healthy');
                } else {
                    $this->warn("  ⚠️  Meilisearch: HTTP {$httpCode}");
                }
            } catch (\Throwable) {
                $this->warn('  ⚠️  Meilisearch: unreachable');
            }
        } else {
            $this->line('  ⏭️  Meilisearch: not configured');
        }

        $this->newLine();
        if ($allPassed) {
            $this->info('All critical checks passed.');

            return self::SUCCESS;
        }

        $this->error('One or more checks failed.');

        return self::FAILURE;
    }
}
