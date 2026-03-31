<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enterprise Grade GDPR Compliance.
 *
 * Automatically anonymizes personal data for users who have been soft-deleted
 * for more than 30 days, fulfilling the GDPR "Right to Erasure".
 */
class AnonymizeDeletedUsers extends Command
{
    protected $signature = 'gdpr:anonymize-deleted';

    protected $description = 'Anonymize personal data for users soft-deleted for more than 30 days';

    public function handle()
    {
        $cutoffDate = now()->subDays(30);

        $usersToAnonymize = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoffDate)
            ->where('is_anonymized', false)
            ->get();

        if ($usersToAnonymize->isEmpty()) {
            $this->info('No users to anonymize.');

            return;
        }

        $this->info("Anonymizing {$usersToAnonymize->count()} users...");

        foreach ($usersToAnonymize as $user) {
            DB::transaction(function () use ($user): void {
                $user->update([
                    'firstname' => 'Anonymized',
                    'lastname' => 'User',
                    'email' => 'deleted_'.Str::random(10).'@keyhome.app',
                    'phone_number' => null,
                    'location' => null,
                    'registration_ip' => null,
                    'last_login_ip' => null,
                    'is_anonymized' => true,
                ]);

                // Also clear media (avatars, etc.)
                $user->clearMediaCollection('avatars');
            });
        }

        $this->info('Anonymization complete.');
    }
}
