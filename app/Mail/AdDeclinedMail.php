<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ad $ad,
        public string $reason = '',
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Votre annonce n\'a pas été approuvée : '.$this->ad->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ad_declined',
            with: [
                'authorName' => $this->ad->user->firstname ?? 'Utilisateur',
                'adTitle' => $this->ad->title,
                'reason' => $this->reason,
            ],
        );
    }
}
