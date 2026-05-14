<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundConfirmationMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public Refund $refund,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('facturation'),
            subject: __('emails.refund.subject', ['amount' => number_format((float) $this->refund->amount, 0, ',', ' ')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-confirmation',
            with: $this->withUnsubscribe(),
        );
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

    protected function resolveRecipientUser(): ?User
    {
        /** @var User|null $user */
        $user = $this->refund->user;

        return $user instanceof User ? $user : null;
    }
}
