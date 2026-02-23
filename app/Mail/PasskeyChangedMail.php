<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasskeyChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $passkeyName,
        public readonly string $primaryEmailAddress,
        public readonly string $action,
        public readonly ?string $greetingName = null,
    ) {}

    public function envelope(): Envelope
    {
        $verb = $this->action === 'added' ? 'ajoutée' : 'supprimée';

        return new Envelope(
            subject: 'Votre clé d\'accès '.config('app.name').' a été '.$verb,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passkey-changed',
        );
    }
}
