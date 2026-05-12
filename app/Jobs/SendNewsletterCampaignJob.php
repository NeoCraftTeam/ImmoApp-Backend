<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewsletterCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public int $maxExceptions = 3;

    public function __construct(public NewsletterCampaign $campaign)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $recipientsCount = 0;

        NewsletterSubscriber::query()
            ->whereNotNull('confirmed_at')
            ->whereNull('unsubscribed_at')
            ->chunkById(100, function ($subscribers) use (&$recipientsCount): void {
                foreach ($subscribers as $subscriber) {
                    SendNewsletterEmailJob::dispatch($subscriber, $this->campaign);
                    $recipientsCount++;
                }
            });

        $this->campaign->update([
            'recipients_count' => $recipientsCount,
            'sent_at' => now(),
        ]);

        Log::info("NewsletterCampaign {$this->campaign->id}: dispatched {$recipientsCount} email jobs.");
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNewsletterCampaignJob failed', [
            'campaign_id' => $this->campaign->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->campaign->update(['sent_at' => null]);
    }
}
