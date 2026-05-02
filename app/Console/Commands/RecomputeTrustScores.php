<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\TrustScoreConsentMissingException;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Console\Command;

final class RecomputeTrustScores extends Command
{
    protected $signature = 'trustscore:recompute
        {--user= : Recompute for a specific user ID}
        {--chunk=500 : Number of users per chunk}';

    protected $description = 'Recompute TrustScores for all consenting users (nightly cron)';

    public function handle(TrustScoreService $service): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User {$userId} not found.");

                return self::FAILURE;
            }

            try {
                $service->compute($user);
            } catch (TrustScoreConsentMissingException $e) {
                $this->error("User {$userId} has not consented to TrustScore computation.");

                return self::FAILURE;
            }

            $this->info("Recomputed TrustScore for {$user->email}: score={$service->getOrCompute($user)['score']}");

            return self::SUCCESS;
        }

        $chunkSize = (int) $this->option('chunk');
        $processed = 0;
        $errors = 0;

        $this->info('Starting TrustScore recomputation for all consenting users...');

        User::query()
            ->where('trust_score_consent', true)
            ->where('is_active', true)
            ->chunkById($chunkSize, function ($users) use ($service, &$processed, &$errors): void {
                foreach ($users as $user) {
                    try {
                        $service->compute($user);
                        $processed++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn("Failed for user {$user->id}: {$e->getMessage()}");
                    }
                }

                $this->info("Processed chunk: {$processed} users so far...");
            });

        $this->info("Done. Processed: {$processed}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
