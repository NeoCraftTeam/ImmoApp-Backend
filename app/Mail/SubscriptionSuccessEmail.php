<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $subscription)
    {
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
            markdown: 'emails.subscription.success',
            with: [
                'agencyName' => $this->subscription->agency->name,
                'planName' => $this->subscription->plan->name,
                'amount' => number_format((float) $this->subscription->amount_paid, 0, ',', ' '),
                'period' => $this->subscription->billing_period === 'yearly' ? 'Annuel' : 'Mensuel',
                'endsAt' => $this->subscription->ends_at->format('d/m/Y'),
            ]
        );
    }
}
