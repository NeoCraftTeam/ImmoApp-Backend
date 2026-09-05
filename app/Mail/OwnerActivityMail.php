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
 * Landlord counterpart to {@see AbandonedSearchMail}, on the same 48 h trigger.
 *
 * The existing owner win-back mails (D7/D14/D30) argue that the landlord should
 * come back. This one does not argue: it reports that tenants looked at their
 * property while they were away, and how many. On a marketplace the supply side
 * returns for evidence of demand, not for encouragement — so the mail is only
 * sent when there is something real to report, and `SendEngagementEmails` skips
 * the owner entirely when the view count is zero.
 */
final class OwnerActivityMail extends Mailable implements ShouldQueue
{
    use Concerns\FormatsAdCards;
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    /** @var array<int, array{title: string, price: string, location: string, url: string, image: string|null}> */
    public array $adCards = [];

    /**
     * @param  iterable<int, Ad>  $topAds  the owner's most viewed listings
     */
    public function __construct(
        public User $user,
        public int $viewCount,
        public int $favoriteCount = 0,
        public int $unansweredMessages = 0,
        iterable $topAds = [],
    ) {
        $this->onQueue('emails');
        $this->adCards = $this->formatAdCards($topAds);
        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        $subject = $this->viewCount === 1
            ? __('emails.owner_activity.subject_one')
            : __('emails.owner_activity.subject', ['views' => $this->viewCount]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.owner-activity',
            with: $this->withUnsubscribe([
                'adCards' => $this->adCards,
                'theme' => MailTheme::owner(),
                'panelUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/owner',
            ]),
        );
    }

    protected function resolveRecipientUser(): User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'engagement_emails';
    }
}
