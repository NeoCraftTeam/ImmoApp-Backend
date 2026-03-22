<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\NewsletterBroadcastMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendNewsletterCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public NewsletterCampaign $campaign)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $subscribers = NewsletterSubscriber::query()
            ->whereNotNull('confirmed_at')
            ->whereNull('unsubscribed_at')
            ->get();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)
                ->send(new NewsletterBroadcastMail($this->campaign, $subscriber));
        }

        $this->campaign->update([
            'recipients_count' => $subscribers->count(),
            'sent_at' => now(),
        ]);
    }
}
