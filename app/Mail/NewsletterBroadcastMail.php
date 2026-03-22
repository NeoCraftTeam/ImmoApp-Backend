<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterBroadcastMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public NewsletterCampaign $campaign,
        public NewsletterSubscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.broadcast',
            with: [
                'body' => $this->campaign->body,
                'name' => $this->subscriber->name,
                'unsubscribeUrl' => url("/api/v1/newsletter/unsubscribe/{$this->subscriber->token}"),
            ],
        );
    }
}
