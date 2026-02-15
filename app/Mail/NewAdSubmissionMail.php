<?php

namespace App\Mail;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAdSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $author;

    /**
     * Create a new message instance.
     */
    public function __construct(/**
       * Create a new message instance.
       */
        public Ad $ad
    ) {
        $this->author = $this->ad->user;

        // Queue only in production/staging environments
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle Annonce : '.$this->ad->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_ad_submission',
            with: [
                'authorName' => $this->author->fullname,
                'authorEmail' => $this->author->email,
                'authorRole' => $this->author->role->getLabel(),
                'authorType' => $this->author->type?->getLabel() ?? 'N/A',
                'adTitle' => $this->ad->title,
                'adPrice' => number_format((float) $this->ad->price, 0, ',', ' ').' FCFA',
                'adType' => $this->ad->ad_type->name ?? 'N/A',
                'adQuarter' => $this->ad->quarter->name ?? 'N/A',
                'url' => self::getAdminUrl($this->ad),
            ],
        );
    }

    /**
     * Safely resolve the Filament admin URL for the ad.
     * Returns '#' if Filament routes are not registered (e.g. in tests).
     */
    private static function getAdminUrl(Ad $ad): string
    {
        try {
            return \App\Filament\Admin\Resources\Ads\AdResource::getUrl('edit', ['record' => $ad]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException|\Illuminate\Routing\Exceptions\UrlGenerationException) {
            return '#';
        }
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
