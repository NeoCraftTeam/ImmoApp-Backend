<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Security alert sent to account owner when someone attempts to link
 * a social OAuth provider (Google, Facebook, Apple) to their account.
 *
 * The user should click "Block this attempt" if they don't recognize it.
 */
class OAuthLinkAttemptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userFirstName,
        public readonly string $provider,
        public readonly string $ipAddress,
        public readonly string $attemptedAt,
        public readonly string $secureAccountUrl,
        public readonly ?string $supportEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tentative de liaison de compte '.ucfirst($this->provider).' — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.oauth-link-attempt',
        );
    }
}
