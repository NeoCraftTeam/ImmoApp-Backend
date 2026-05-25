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
 * Owner re-engagement lifecycle email series.
 *
 * Targets landlords who have registered but are becoming inactive:
 *   D+7  — no ad published yet: encourage to post first ad
 *   D+14 — has ad(s) but hasn't logged in for 14 days: remind to update listings
 *   D+30 — deeply inactive: strong win-back with social proof
 *
 * Sent by SendEngagementEmails command (--type=owner-reengagement or --type=all).
 */
class OwnerReEngagementMail extends Mailable implements ShouldQueue
{
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $daysSinceActivity,
        public bool $hasPublishedAd,
        public int $activeAdsCount,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->daysSinceActivity <= 7 && !$this->hasPublishedAd => 'Votre première annonce est à portée de clic — '.config('app.name'),
            $this->daysSinceActivity <= 14 => 'Vos annonces attendent des locataires — '.config('app.name'),
            default => $this->user->firstname.', votre espace bailleur vous attend — '.config('app.name'),
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
            view: 'emails.engagement.owner-re-engagement',
            with: $this->withUnsubscribe([
                'user' => $this->user,
                'daysSinceActivity' => $this->daysSinceActivity,
                'hasPublishedAd' => $this->hasPublishedAd,
                'activeAdsCount' => $this->activeAdsCount,
                'panelUrl' => $base.'/owner/dashboard',
                'newAdUrl' => $base.'/owner/ads/create',
                'manageAdsUrl' => $base.'/owner/ads',
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
