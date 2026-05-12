<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class SendTestPush extends Command
{
    protected $signature = 'push:test
        {--user= : User ID to send to (sends to all subscriptions if omitted)}
        {--broadcast : Send to all subscribed users}
        {--message= : Custom notification body}';

    protected $description = 'Send a test push notification to verify the WebPush setup';

    public function handle(WebPushService $pushService): int
    {
        $message = $this->option('message') ?: '🏠 Ceci est une notification de test KeyHome !';

        $payload = [
            'title' => 'KeyHome — Test',
            'body' => $message,
            'tag' => 'test-'.time(),
            'url' => '/admin',
        ];

        if ($this->option('broadcast')) {
            $total = PushSubscription::query()->count();
            $this->info("Broadcasting to {$total} subscription(s)...");

            $sent = $pushService->broadcast($payload);
            $this->info("✓ Sent to {$sent}/{$total} subscription(s).");

            return self::SUCCESS;
        }

        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);

            if (!$user) {
                $this->error("User #{$userId} not found.");

                return self::FAILURE;
            }

            $subCount = $user->pushSubscriptions()->count();
            $this->info("Sending to {$user->fullname} ({$subCount} subscription(s))...");

            $sent = $pushService->sendToUser($user, $payload);
            $this->info("✓ Sent to {$sent}/{$subCount} device(s).");

            return self::SUCCESS;
        }

        $subscriptions = PushSubscription::query()->with('subscribable')->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No push subscriptions found. Open the app in a browser and allow notifications first.');

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'User', 'Endpoint (truncated)', 'Created'],
            $subscriptions->map(fn (PushSubscription $s) => [
                $s->id,
                ($s->subscribable instanceof User ? $s->subscribable->fullname : null) ?? 'N/A',
                substr($s->endpoint, 0, 60).'...',
                $s->created_at?->diffForHumans(),
            ])
        );

        $total = $subscriptions->count();
        $this->info("Sending to all {$total} subscription(s)...");

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            if ($pushService->sendToSubscription($subscription, $payload)) {
                $subscription->update(['last_used_at' => now()]);
                $sent++;
            }
        }

        $this->info("✓ Sent to {$sent}/{$total} subscription(s).");

        return self::SUCCESS;
    }
}
