<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

/**
 * Provides typed sender addresses for Mailables.
 *
 * Six senders are supported, each configured via .env:
 *   - 'noreply'       → MAIL_NOREPLY_ADDRESS       (transactionnel pur — OTP, reçus, sécurité)
 *   - 'notifications' → MAIL_NOTIFICATIONS_ADDRESS (alertes système — annonces, rappels, abonnements)
 *   - 'marketing'     → MAIL_MARKETING_ADDRESS     (newsletters, campagnes, promotions)
 *   - 'support'       → MAIL_SUPPORT_ADDRESS       (service client, signalements, formulaire contact)
 *   - 'bailleurs'     → MAIL_BAILLEURS_ADDRESS     (onboarding bailleurs & agences)
 *   - 'admin'         → MAIL_ADMIN_ADDRESS         (notifications internes, modération)
 *
 * Usage in a Mailable's envelope():
 *
 *     use Concerns\HasSender;
 *
 *     public function envelope(): Envelope
 *     {
 *         return new Envelope(
 *             from: $this->senderFrom('notifications'),
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
