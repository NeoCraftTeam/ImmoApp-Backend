<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserOnboardingCommand extends Command
{
    protected $signature = 'user:reset-onboarding {email : The user email address}';

    protected $description = 'Reset onboarding_completed_at to null for a user (useful for testing WelcomeModal)';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        $user->update(['onboarding_completed_at' => null]);
        $this->info("Onboarding reset for [{$email}]. Refresh the app to see the WelcomeModal.");

        return self::SUCCESS;
    }
}
