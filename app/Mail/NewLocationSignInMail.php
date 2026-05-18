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

/**
 * Sent when a user authenticates from a new geographic location (country / city).
 * Inspired by Binance's "new location" security alert pattern.
 */
class NewLocationSignInMail extends Mailable implements ShouldQueue
{
    use HasSender;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $city,
        public readonly string $country,
        public readonly string $ipAddress,
        public readonly string $device,
        public readonly string $browser,
        public readonly string $operatingSystem,
        public readonly string $loginAt,
        public readonly ?string $secureAccountUrl = null,
        public readonly ?string $supportEmail = null,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        $locationParts = array_filter([
            $this->city !== 'Inconnue' ? $this->city : null,
            $this->country !== 'Inconnu' ? $this->country : null,
        ]);

        $subject = $locationParts !== []
            ? 'Connexion depuis '.implode(', ', $locationParts).' — '.config('app.name')
            : 'Connexion depuis un nouvel emplacement — '.config('app.name');

        return new Envelope(
            from: $this->senderFrom('notifications'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-location-signin',
        );
    }
}
