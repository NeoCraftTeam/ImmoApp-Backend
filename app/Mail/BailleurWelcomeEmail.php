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

class BailleurWelcomeEmail extends Mailable implements ShouldQueue
{
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue sur KeyHome - Espace Bailleur',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bailleur-welcome',
            with: $this->withUnsubscribe(),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'welcome_emails';
    }

    public function attachments(): array
    {
        return [];
    }
}
