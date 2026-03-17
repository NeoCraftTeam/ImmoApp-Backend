<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SearchAlertMatchMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $adUrl;

    public string $formattedPrice;

    public function __construct(
        public Ad $ad,
        public SearchAlert $alert,
        public User $recipient
    ) {
        $this->adUrl = config('app.frontend_url').'/ads/'.urlencode((string) $ad->id).'/'.urlencode($ad->slug);
        $this->formattedPrice = number_format($ad->price ?? 0, 0, ',', ' ').' FCFA';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Une annonce correspond à vos critères !',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.search-alert-match',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
