<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent once per digest run: one email covers all alert matches
 * accumulated since the previous digest.
 *
 * @param array<string, array{alert: SearchAlert, ads: Ad[], summary: string}> $groups
 */
final class SearchAlertDigestMail extends Mailable implements ShouldQueue
{
    use Concerns\HasLocale;
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    /** @var array<string, array{alert: SearchAlert, formattedAds: array<int, array<string, mixed>>, summary: string, extraCount: int}> */
    public array $enrichedGroups;

    public string $recipientFirstname;

    public int $totalAds;

    /**
     * @param  array<string, array{alert: SearchAlert, ads: Ad[], summary: string}>  $groups
     */
    public function __construct(array $groups, public readonly User $recipient)
    {
        $this->onQueue('emails');
        $this->recipientFirstname = (string) ($recipient->firstname ?? '');
        $this->totalAds           = collect($groups)->sum(fn ($g) => count($g['ads']));

        $this->enrichedGroups = collect($groups)->map(function (array $group): array {
            return [
                'alert'   => $group['alert'],
                'summary' => $group['summary'],
                'formattedAds' => collect($group['ads'])->take(5)->map(function (Ad $ad): array {
                    return [
                        'title'          => $ad->title,
                        'formattedPrice' => number_format((float) ($ad->price ?? 0), 0, ',', ' ').' FCFA',
                        'surface'        => $ad->surface_area,
                        'bedrooms'       => $ad->bedrooms,
                        'city'           => $ad->quarter?->city?->name,
                        'quarter'        => $ad->quarter?->name,
                        'url'            => config('app.frontend_url').'/ads/'.urlencode((string) $ad->id).'/'.urlencode($ad->slug),
                    ];
                })->all(),
                'extraCount' => max(0, count($group['ads']) - 5),
            ];
        })->all();

        $this->applyRecipientLocale();
    }

    public function envelope(): Envelope
    {
        $subject = $this->totalAds === 1
            ? '1 nouvelle annonce correspond à vos alertes — KeyHome'
            : "{$this->totalAds} nouvelles annonces correspondent à vos alertes — KeyHome";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.search-alert-digest',
            with: $this->withUnsubscribe(),
        );
    }

    protected function resolveRecipientUser(): User
    {
        return $this->recipient;
    }

    protected function emailCategory(): string
    {
        return 'search_alerts';
    }

    public function attachments(): array
    {
        return [];
    }
}
