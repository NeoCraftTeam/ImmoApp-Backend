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

class InactivityReminderMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $daysSinceLogin,
        public int $newAdsCount,
    ) {
        $this->onQueue('emails');
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('marketing'),
            subject: __('emails.inactivity.subject', [
                'name' => $this->user->firstname,
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.inactivity-reminder',
            with: $this->withUnsubscribe([
                'daysSinceLogin' => $this->daysSinceLogin,
                'newAdsCount' => $this->newAdsCount,
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
