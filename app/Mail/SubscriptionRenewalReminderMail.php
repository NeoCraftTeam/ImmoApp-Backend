<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Agency;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminderMail extends Mailable implements ShouldQueue
{
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $paymentUrl,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action requise — renouvelez votre abonnement '.config('app.name'),
        );
    }

    public function content(): Content
    {
        /** @var Agency|null $agency */
        $agency = $this->subscription->agency;

        return new Content(
            view: 'emails.subscription.renewal-reminder',
            with: $this->withUnsubscribe([
                'agencyName' => $agency->name ?? 'Agence',
                'planName' => $this->subscription->plan->name ?? 'Plan',
                'planPrice' => number_format((float) ($this->subscription->amount_paid ?? 0), 0, ',', ' '),
                'billingPeriod' => $this->subscription->billing_period === 'yearly' ? 'annuel' : 'mensuel',
                'endsAt' => $this->subscription->ends_at?->format('d/m/Y') ?? 'N/A',
                'paymentUrl' => $this->paymentUrl,
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->subscription->agency?->users->first();
    }

    protected function emailCategory(): string
    {
        return 'subscription_updates';
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
