<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuccessEmail extends Mailable implements ShouldQueue
{
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription)
    {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de votre abonnement sur KeyHome',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.success',
            with: $this->withUnsubscribe([
                'agencyName' => $this->subscription->agency->name ?? 'Agence',
                'planName' => $this->subscription->plan->name ?? 'Plan',
                'amount' => number_format((float) $this->subscription->amount_paid, 0, ',', ' '),
                'period' => $this->subscription->billing_period === 'yearly' ? 'Annuel' : 'Mensuel',
                'endsAt' => $this->subscription->ends_at?->format('d/m/Y') ?? 'N/A',
            ])
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
}
