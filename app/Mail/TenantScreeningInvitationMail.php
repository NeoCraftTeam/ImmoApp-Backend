<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invites a prospective tenant to upload their screening documents through
 * the public, token-gated screening page. Sent when a landlord creates a
 * screening request from their lease-contract dashboard.
 */
class TenantScreeningInvitationMail extends Mailable implements ShouldQueue
{
    use HasSender;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $requiredDocumentLabels
     */
    public function __construct(
        public readonly string $tenantName,
        public readonly string $actionUrl,
        public readonly int $expiresInDays,
        public readonly array $requiredDocumentLabels,
        public readonly ?string $landlordNotes = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('notifications'),
            subject: 'Votre dossier locataire sur '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.screening-invitation',
        );
    }
}
