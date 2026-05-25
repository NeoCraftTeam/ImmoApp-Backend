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
 * Milestone email sent once when an owner (AGENT role) completes their
 * onboarding / profile for the first time.
 *
 * Triggered from UserPreferenceController::completeOnboarding() when
 * onboarding_completed_at transitions null → timestamp for an owner.
 */
class OwnerProfileCompletedMail extends Mailable implements ShouldQueue
{
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('bailleurs'),
            subject: 'Profil complété — publiez votre première annonce sur '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url'), '/');

        return new Content(
            view: 'emails.owner-profile-completed',
            with: $this->withUnsubscribe([
                'firstName' => $this->user->firstname ?? 'Bailleur',
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
        return 'onboarding_emails';
    }

    public function attachments(): array
    {
        return [];
    }
}
