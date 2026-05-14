<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use App\Mail\Concerns\HasUnsubscribeLinks;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWelcomeEmail extends Mailable implements ShouldQueue
{
    use HasSender, HasUnsubscribeLinks, Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('admin'),
            subject: 'Bienvenue sur le panneau d\'administration KeyHome',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-welcome',
            with: $this->withUnsubscribe(),
        );
    }

    public function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    public function emailCategory(): string
    {
        return 'system';
    }

    public function attachments(): array
    {
        return [];
    }
}
