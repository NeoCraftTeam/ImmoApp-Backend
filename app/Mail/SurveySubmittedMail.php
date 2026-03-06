<?php

namespace App\Mail;

use App\Models\Survey;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveySubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public User $user,
    ) {
        if (app()->environment(['production', 'staging', 'development'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Merci pour votre participation au sondage : '.$this->survey->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey-submitted',
            with: [
                'userName' => $this->user->firstname,
                'surveyTitle' => $this->survey->title,
            ],
        );
    }
}
