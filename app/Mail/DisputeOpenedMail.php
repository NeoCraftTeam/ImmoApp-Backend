<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasLocale;
use App\Mail\Concerns\HasSender;
use App\Mail\Concerns\HasUnsubscribeLinks;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class DisputeOpenedMail extends Mailable implements ShouldQueue
{
    use HasLocale, HasSender, HasUnsubscribeLinks, Queueable, SerializesModels;

    public function __construct(
        public readonly Dispute $dispute,
        public readonly User $recipient,
    ) {
        $this->applyRecipientLocale();
        $this->onQueue('emails');
    }

    protected function resolveRecipientUser(): User
    {
        return $this->recipient;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('notifications'),
            subject: "Litige ouvert — {$this->dispute->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dispute-opened',
            with: $this->withUnsubscribe([
                'dispute' => $this->dispute,
                'recipient' => $this->recipient,
                'disputeUrl' => rtrim((string) config('app.frontend_url', 'https://keyhome.app'), '/').'/litiges/'.$this->dispute->id,
            ]),
        );
    }
}
