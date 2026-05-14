<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasSender;
use App\Mail\Concerns\HasUnsubscribeLinks;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionInvoiceMail extends Mailable implements ShouldQueue
{
    use HasSender, HasUnsubscribeLinks, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('facturation'),
            subject: config('app.name').' — Facture '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.invoice',
            with: $this->withUnsubscribe(),
        );
    }

    public function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    public function emailCategory(): string
    {
        return 'billing';
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
