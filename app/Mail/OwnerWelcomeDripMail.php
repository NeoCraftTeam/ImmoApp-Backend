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

/**
 * Owner-specific welcome drip series (D+1, D+3, D+7 after registration).
 *
 * Unlike WelcomeDripMail which targets clients (search flow), this series
 * guides landlords toward publishing their first ad, adding photos/tours,
 * and configuring visit slots — the landlord activation funnel.
 *
 * Sent by SendEngagementEmails command (--type=owner-drip or --type=all).
 */
class OwnerWelcomeDripMail extends Mailable implements ShouldQueue
{
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $day,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->day) {
            3 => 'Boostez votre annonce avec de belles photos — '.config('app.name'),
            7 => 'Votre première annonce peut être en ligne aujourd\'hui — '.config('app.name'),
            default => 'Comment publier votre premier bien en 5 minutes — '.config('app.name'),
        };

        return new Envelope(
            from: $this->senderFrom('bailleurs'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url'), '/');

        return new Content(
            view: 'emails.engagement.owner-welcome-drip',
            with: $this->withUnsubscribe([
                'day' => $this->day,
                'user' => $this->user,
                'panelUrl' => $base.'/owner/dashboard',
                'newAdUrl' => $base.'/owner/ads/create',
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

    public function attachments(): array
    {
        return [];
    }
}
