<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public \App\Models\Subscription $subscription, public int $daysLeft)
    {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rappel : Votre abonnement expire bientôt',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        /** @var \App\Models\Agency|null $agency */
        $agency = $this->subscription->agency;

        return new Content(
            view: 'emails.subscription.expiring',
            with: [
                'agencyName' => $agency->name ?? 'Agence',
                'planName' => $this->subscription->plan->name ?? 'Plan',
                'planPrice' => number_format((float) ($this->subscription->plan?->price ?? 0), 0, ',', ' '),
                'days' => $this->daysLeft,
                'endsAt' => $this->subscription->ends_at?->format('d/m/Y') ?? 'N/A',
                'renewalUrl' => rtrim(config('app.url'), '/').'/agency/'.($agency?->slug ?? $agency?->id ?? '').'/abonnement',
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
