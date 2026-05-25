<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewDeviceSignInMail extends Mailable implements ShouldQueue
{
    use HasSender;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $deviceType,
        public readonly string $browserName,
        public readonly string $operatingSystem,
        public readonly string $location,
        public readonly string $ipAddress,
        public readonly string $sessionCreatedAt,
        public readonly ?string $userName = null,
        public readonly ?string $signInMethod = null,
        public readonly ?string $revokeSessionUrl = null,
        public readonly ?string $supportEmail = null,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('notifications'),
            subject: 'Connexion depuis un nouvel appareil — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-device-signin',
        );
    }
}
