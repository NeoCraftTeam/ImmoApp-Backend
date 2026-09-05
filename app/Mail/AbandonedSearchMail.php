<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\User;
use App\Support\MailTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Client win-back, 48 hours after someone browsed and did not come back.
 *
 * The one question worth asking at that point is the one a human would ask:
 * did you find the place? Either answer is useful — "yes" earns a congratulation
 * and an invitation to pause the alerts, "no" earns the search they abandoned,
 * with the very flats they were looking at reprinted so the mail is a
 * continuation of their session rather than an advert.
 *
 * `$recentAds` takes `Ad` models but keeps none: they are flattened to plain
 * arrays here, while the relations are still warm. See {@see Concerns\FormatsAdCards}.
 */
class AbandonedSearchMail extends Mailable implements ShouldQueue
{
    use Concerns\FormatsAdCards;
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    /** @var array<int, array{title: string, price: string, location: string, url: string, image: string|null}> */
    public array $adCards = [];

    /**
     * @param  iterable<int, Ad>  $recentAds  the ads this user actually looked at
     */
    public function __construct(
        public User $user,
        public int $matchingAdsCount,
        public string $searchUrl,
        iterable $recentAds = [],
    ) {
        $this->onQueue('emails');
        $this->adCards = $this->formatAdCards($recentAds);
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.abandoned_search.subject', [
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.abandoned-search',
            with: $this->withUnsubscribe([
                'matchingAdsCount' => $this->matchingAdsCount,
                'searchUrl' => $this->searchUrl,
                'adCards' => $this->adCards,
                'theme' => MailTheme::client(),
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
