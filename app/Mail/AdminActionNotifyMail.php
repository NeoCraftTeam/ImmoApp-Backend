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
 * Sent to other admins when a fellow admin performs an action.
 */
class AdminActionNotifyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{event: string, entity: string, entity_name: string, description: string, changes: array<string, mixed>, date: string}  $details
     */
    public function __construct(
        public User $actor,
        public User $recipient,
        public array $details,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KeyHome — Action admin : '.$this->details['description'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-action-notify',
            with: [
                'recipientName' => $this->recipient->firstname,
                'actorName' => $this->actor->firstname.' '.$this->actor->lastname,
                'actorEmail' => $this->actor->email,
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
