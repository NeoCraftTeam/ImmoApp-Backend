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
use Illuminate\Support\Str;

class AdDeclinedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Markdown reason rendered to safe HTML for the email template. */
    public readonly string $reasonHtml;

    public function __construct(
        public Ad $ad,
        public string $reason = '',
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }

        // Convert the Markdown written by the admin into sanitised HTML.
        // str()->markdown() uses league/commonmark (bundled with Laravel).
        $this->reasonHtml = $reason !== ''
            ? (string) Str::markdown($reason, ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : '';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre annonce KeyHome n\'a pas été approuvée : '.$this->ad->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ad_declined',
            with: [
                'authorName' => $this->ad->user->firstname ?? 'Utilisateur',
                'adTitle' => $this->ad->title,
                'reasonHtml' => $this->reasonHtml,
            ],
        );
    }
}
