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
 * Sent when a user authenticates from a new geographic location (country / city).
 * Inspired by Binance's "new location" security alert pattern.
 */
class NewLocationSignInMail extends Mailable implements ShouldQueue
{
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
        return new Envelope(
            subject: 'Nouvelle connexion depuis '.$this->city.', '.$this->country.' — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-location-signin',
        );
    }
}
