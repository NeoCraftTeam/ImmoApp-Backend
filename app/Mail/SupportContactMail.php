<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the support inbox when a visitor submits the public contact form.
 *
 * - Reply-To is set to the visitor's email so support can reply directly.
 * - From stays on the application's transactional sender (DMARC/SPF aligned).
 */
final class SupportContactMail extends Mailable implements ShouldQueue
{
    use HasSender, Queueable, SerializesModels;

    public function __construct(
        public readonly string $contactName,
        public readonly string $contactEmail,
        public readonly string $contactSubject,
        public readonly string $contactMessage,
        public readonly ?string $sourceIp = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $sourceUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('support'),
            subject: '[Contact] '.$this->contactSubject.' — '.$this->contactName,
            replyTo: [new Address($this->contactEmail, $this->contactName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-contact',
            with: [
                'contactName' => $this->contactName,
                'contactEmail' => $this->contactEmail,
                'contactSubject' => $this->contactSubject,
                'contactMessage' => $this->contactMessage,
                'sourceIp' => $this->sourceIp,
                'userAgent' => $this->userAgent,
                'sourceUrl' => $this->sourceUrl,
            ],
        );
    }

    /** @return array<int, mixed> */
    public function attachments(): array
    {
        return [];
    }
}
