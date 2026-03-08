<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditPurchaseConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public PointPackage $package,
        public Payment $payment,
        public int $newBalance,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Achat de crédits confirmé — '.$this->package->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credit-purchase-confirmation',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
