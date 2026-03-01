<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the admin who performed the action ("Vous avez effectué…").
 */
class AdminActionPerformedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{event: string, entity: string, entity_name: string, description: string, changes: array<string, mixed>, date: string}  $details
     */
    public function __construct(
        public User $actor,
        public array $details,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KeyHome — Confirmation de votre action : '.$this->details['description'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-action-performed',
            with: [
                'actorName' => $this->actor->firstname.' '.$this->actor->lastname,
                'event' => $this->details['event'],
                'entity' => $this->details['entity'],
                'entityName' => $this->details['entity_name'],
                'description' => $this->details['description'],
                'changes' => $this->details['changes'],
                'date' => $this->details['date'],
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
