<?php

namespace App\Mail;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdSubmissionConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $author;

    /**
     * Create a new message instance.
     */
    public function __construct(
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
            subject: 'Confirmation de réception de votre annonce : '.$this->ad->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ad_submission_confirmation',
            with: [
                'authorName' => $this->author->firstname, // Close/personal salutation
                'adTitle' => $this->ad->title,
            ],
        );
    }
}
