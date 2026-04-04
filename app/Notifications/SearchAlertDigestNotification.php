<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\SearchAlertDigestMail;
use App\Models\Ad;
use App\Models\SearchAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Batched digest notification: one notification per user covering all
 * pending alert matches since the last digest run.
 *
 * Database payload is structured so the frontend can render a rich card
 * showing each alert group with its AI-generated summary and ad list.
 *
 * @param  array<string, array{alert: SearchAlert, ads: Ad[], summary: string}>  $groups
 */
final class SearchAlertDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, array{alert: SearchAlert, ads: Ad[], summary: string}> $groups */
    public function __construct(public readonly array $groups) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): SearchAlertDigestMail
    {
        return new SearchAlertDigestMail($this->groups, $notifiable)
            ->to($notifiable->email, $notifiable->firstname);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $totalAds = collect($this->groups)->sum(fn ($g) => count($g['ads']));
        $alertCount = count($this->groups);

        $body = $totalAds === 1
            ? '1 nouvelle annonce correspond à vos alertes.'
            : "{$totalAds} nouvelles annonces correspondent à vos ".($alertCount === 1 ? '1 alerte' : "{$alertCount} alertes").'.';

        return (new WebPushMessage)
            ->title('Vos alertes immobilières')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body($body)
            ->tag('search-alert-digest-'.now()->format('YmdH'))
            ->data(['url' => config('app.frontend_url').'/search-alerts']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $totalAds = collect($this->groups)->sum(fn ($g) => count($g['ads']));

        $groupsData = collect($this->groups)->map(function (array $group): array {
            /** @var SearchAlert $alert */
            $alert = $group['alert'];

            return [
                'alert_id' => $alert->id,
                'alert_label' => $alert->label ?? $this->alertDescription($alert),
                'summary' => $group['summary'],
                'ad_count' => count($group['ads']),
                'ads' => collect($group['ads'])->take(3)->map(fn (Ad $ad): array => [
                    'id' => $ad->id,
                    'slug' => $ad->slug,
                    'title' => $ad->title,
                    'price' => $ad->price,
                    'city' => $ad->quarter?->city?->name,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'type' => 'search_alert_digest',
            'title' => 'Vos alertes immobilières',
            'message' => $totalAds === 1
                ? '1 nouvelle annonce correspond à vos alertes.'
                : "{$totalAds} nouvelles annonces correspondent à vos alertes.",
            'total_ads' => $totalAds,
            'group_count' => count($this->groups),
            'groups' => $groupsData,
        ];
    }

    private function alertDescription(SearchAlert $alert): string
    {
        $parts = array_filter([
            $alert->type_name,
            $alert->city_name,
            $alert->price_max ? 'max '.number_format($alert->price_max, 0, ',', ' ').' FCFA' : null,
        ]);

        return implode(' à ', $parts) ?: 'Alerte immobilière';
    }
}
