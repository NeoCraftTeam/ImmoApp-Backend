<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $propertyTitle,
        public Carbon $appointmentDate,
        public string $address,
        public string $detailsUrl,
    ) {
        $this->onQueue('emails');
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.appointment_reminder.subject', [
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.appointment-reminder',
            with: $this->withUnsubscribe([
                'propertyTitle' => $this->propertyTitle,
                'appointmentDate' => $this->appointmentDate->translatedFormat('l j F Y à H:i'),
                'address' => $this->address,
                'detailsUrl' => $this->detailsUrl,
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'engagement_emails';
    }
}
