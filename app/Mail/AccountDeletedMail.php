<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent immediately after an account is soft-deleted (GDPR erasure).
 *
 * Uses the primary (pink) layout for customers and the teal layout for owners/agents.
 */
final class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly UserRole $userRole,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre compte KeyHome a été supprimé',
        );
    }

    public function content(): Content
    {
        $isOwner = $this->userRole === UserRole::AGENT || $this->userRole === UserRole::ADMIN;

        return new Content(
            view: $isOwner ? 'emails.account-deleted-owner' : 'emails.account-deleted',
            with: [
                'userName' => $this->userName,
            ],
        );
    }
}
