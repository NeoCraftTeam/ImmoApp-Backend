<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewsletterBroadcastMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletterEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 30;

    public int $maxExceptions = 3;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public NewsletterCampaign $campaign,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->subscriber->email)
            ->send(new NewsletterBroadcastMail($this->campaign, $this->subscriber));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNewsletterEmailJob failed', [
            'subscriber_id' => $this->subscriber->id,
            'campaign_id' => $this->campaign->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
