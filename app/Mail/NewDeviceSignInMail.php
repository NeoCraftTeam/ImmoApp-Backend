<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewDeviceSignInMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $deviceType,
        public readonly string $browserName,
        public readonly string $operatingSystem,
        public readonly string $location,
        public readonly string $ipAddress,
        public readonly string $sessionCreatedAt,
        public readonly ?string $signInMethod = null,
        public readonly ?string $revokeSessionUrl = null,
        public readonly ?string $supportEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle connexion à votre compte '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-device-signin',
        );
    }
}
