<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

/**
 * Provides typed sender addresses for Mailables.
 *
 * Three senders are supported, each configured via .env:
 *   - 'noreply'   → MAIL_NOREPLY_ADDRESS   (default for all transactional emails)
 *   - 'support'   → MAIL_SUPPORT_ADDRESS   (admin notifications, contact forms, reports)
 *   - 'marketing' → MAIL_MARKETING_ADDRESS (welcome emails, newsletters, engagement)
 *
 * Usage in a Mailable's envelope():
 *
 *     use Concerns\HasSender;
 *
 *     public function envelope(): Envelope
 *     {
 *         return new Envelope(
 *             from: $this->senderFrom('support'),
 *             subject: '...',
 *         );
 *     }
 */
trait HasSender
{
    protected function senderFrom(string $type = 'noreply'): Address
    {
        return new Address(
            (string) config("mail.senders.{$type}.address", config('mail.from.address')),
            (string) config("mail.senders.{$type}.name", config('mail.from.name')),
        );
    }
}
