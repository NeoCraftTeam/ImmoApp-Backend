<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstAdCelebrationMail extends Mailable implements ShouldQueue
{
    use Concerns\HasSender;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public User $author;

    public function __construct(public Ad $ad)
    {
        $this->author = $this->ad->user
            ?? new User(['firstname' => 'Propriétaire', 'lastname' => '', 'email' => 'unknown@keyhome.cm']);

        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('marketing'),
            subject: 'Bravo pour votre première annonce sur '.config('app.name').' !',
        );
    }

    public function content(): Content
    {
        $domain = config('filament.panels.agency_domain');
        $panelUrl = $domain
            ? 'https://'.$domain
            : rtrim((string) config('app.url'), '/').'/agency';

        return new Content(
            view: 'emails.first-ad-celebration',
            with: $this->withUnsubscribe([
                'authorName' => $this->author->firstname,
                'adTitle' => $this->ad->title,
                'panelUrl' => $panelUrl,
            ]),
        );
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->ad->user;
    }

    protected function emailCategory(): string
    {
        return 'ad_updates';
    }
}
