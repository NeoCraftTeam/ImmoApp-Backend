<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Milestone email sent once when a customer unlocks contact details for the first time (crédits).
 */
class FirstAdUnlockCongratulationsMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Ad $ad,
    ) {
        $this->applyRecipientLocale();
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('notifications'),
            subject: 'Bravo — vous avez débloqué votre première annonce sur '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adPath = $this->ad->slug ? "/ads/{$this->ad->slug}" : "/ads/{$this->ad->id}";

        return new Content(
            view: 'emails.first-ad-unlock-congratulations',
            with: $this->withUnsubscribe([
                'firstName' => $this->user->firstname ?? ' ',
                'adTitle' => $this->ad->title,
                'adUrl' => $base.$adPath,
                'searchUrl' => $base.'/search',
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'retention';
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
