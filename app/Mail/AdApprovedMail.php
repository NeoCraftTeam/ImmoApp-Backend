<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ad $ad
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Votre annonce a été approuvée : '.$this->ad->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ad_approved',
            with: [
                'authorName' => $this->ad->user->firstname ?? 'Utilisateur',
                'adTitle' => $this->ad->title,
                'adPrice' => number_format((float) $this->ad->price, 0, ',', ' ').' FCFA',
            ],
        );
    }
}
