<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmationMail extends Mailable
{
    use HasSender, Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('marketing'),
            subject: 'Bienvenue – Votre abonnement newsletter KeyHome est confirmé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.confirmation',
            with: [
                'name' => $this->subscriber->name,
                'unsubscribeUrl' => url("/api/v1/newsletter/unsubscribe/{$this->subscriber->token}"),
            ],
        );
    }
}
