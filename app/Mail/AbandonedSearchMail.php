<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedSearchMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $matchingAdsCount,
        public string $searchUrl,
    ) {
        $this->onQueue('emails');
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.abandoned_search.subject', [
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.abandoned-search',
            with: $this->withUnsubscribe([
                'matchingAdsCount' => $this->matchingAdsCount,
                'searchUrl' => $this->searchUrl,
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'engagement_emails';
    }
}
