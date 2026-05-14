<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FailedPaymentRetryMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public User $user,
    ) {
        $this->onQueue('emails');
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('facturation'),
            subject: __('emails.failed_payment.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.failed-payment-retry',
            with: $this->withUnsubscribe([
                'amount' => number_format((float) $this->payment->amount, 0, ',', ' '),
                'paymentType' => $this->payment->type,
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'engagement_emails';
    }
}
