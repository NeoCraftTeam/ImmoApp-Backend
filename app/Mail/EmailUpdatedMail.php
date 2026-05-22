<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $newEmailAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Adresse email mise à jour — vérification requise sur '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-updated',
        );
    }
}
