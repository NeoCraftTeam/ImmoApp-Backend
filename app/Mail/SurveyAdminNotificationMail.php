<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyAdminNotificationMail extends Mailable implements ShouldQueue
{
    use HasSender, Queueable, SerializesModels;

    public function __construct(
        public Survey $survey,
        public ?User $respondent,
        /** @var array<int, array{question: string, answer: string}> */
        public array $formattedAnswers,
    ) {
        if (app()->environment(['production', 'staging', 'development'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        $respondentName = $this->respondent
            ? trim($this->respondent->firstname.' '.$this->respondent->lastname)
            : 'Anonyme';

        return new Envelope(
            from: $this->senderFrom('admin'),
            subject: 'Nouveau sondage reçu — '.$respondentName.' a répondu à : '.$this->survey->title,
        );
    }

    public function content(): Content
    {
        $respondentName = $this->respondent
            ? trim($this->respondent->firstname.' '.$this->respondent->lastname)
            : 'Anonyme';

        return new Content(
            view: 'emails.survey-admin-notification',
            with: [
                'surveyTitle' => $this->survey->title,
                'respondentName' => $respondentName,
                'respondentEmail' => $this->respondent !== null ? $this->respondent->email : '—',
                'formattedAnswers' => $this->formattedAnswers,
            ],
        );
    }
}
