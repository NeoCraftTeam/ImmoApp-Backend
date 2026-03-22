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

class PostViewingFeedbackMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $propertyTitle,
        public string $feedbackUrl,
        public string $browseUrl,
    ) {
        $this->onQueue('emails');
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.post_viewing_feedback.subject', [
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.post-viewing-feedback',
            with: $this->withUnsubscribe([
                'propertyTitle' => $this->propertyTitle,
                'feedbackUrl' => $this->feedbackUrl,
                'browseUrl' => $this->browseUrl,
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
