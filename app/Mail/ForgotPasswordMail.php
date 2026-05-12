<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $requestedFrom,
        public readonly string $requestedAt,
        public readonly ?string $userRole = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.forgot_password.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        $layout = $this->userRole === 'agent' ? 'emails.owner-layout' : 'emails.layout';

        return new Content(
            view: 'emails.forgot-password',
            with: ['emailLayout' => $layout, 'isOwner' => $this->userRole === 'agent'],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
