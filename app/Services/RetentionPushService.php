<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdStatus;
use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\LeaseContract;
use App\Models\SearchAlert;
use App\Models\TentativeReservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends behavioral retention push notifications.
 *
 * All 5 triggers are frequency-capped via Redis to avoid notification fatigue.
 *
 * Triggers:
 *  1. win_back           — user has not logged in for 3+ days (has push subscription)
 *  2. search_alert_match — newly published ad matches an active search alert
 *  3. price_drop         — a favorited ad's price dropped by ≥ 5 000 FCFA
 *  4. viewing_reminder   — confirmed viewing appointment is scheduled for tomorrow
 *  5. lease_expiry       — lease contract ends in 30 or 7 days (notify landlord)
 */
final readonly class RetentionPushService
{
    public function __construct(private WebPushService $webPush) {}

    // ─── 1. Win-back dormant users ───────────────────────────────────────────

    public function winBackDormantUsers(): int
    {
        $sent = 0;

        User::query()
            ->has('pushSubscriptions')
            ->whereHas('loginHistories', fn ($q) => $q->where('successful', true))
            ->whereDoesntHave('loginHistories', fn ($q) => $q
                ->where('successful', true)
                ->where('created_at', '>=', now()->subDays(3))
            )
            ->with('pushSubscriptions')
            ->chunk(200, function ($users) use (&$sent): void {
                foreach ($users as $user) {
                    $capKey = "retention_push:win_back:{$user->id}";
                    if ($this->isCapped($capKey)) {
                        continue;
                    }

                    $netFavorites = $this->getNetFavoriteCount($user->id);
                    $body = $netFavorites > 0
                        ? "Vous avez {$netFavorites} annonce(s) sauvegardée(s) qui vous attendent. "
                        : 'Reprenez votre recherche immobilière là où vous vous êtes arrêté(e). ';

                    $result = $this->webPush->sendToUser($user, [
                        'title' => 'On a gardé votre espace ',
                        'body' => $body,
                        'tag' => 'win-back',
                        'url' => '/search',
                    ]);

                    if ($result > 0) {
                        $this->setCap($capKey, 7 * 86400);
                        $sent++;
                        Log::info('[RetentionPush] win_back', ['user_id' => $user->id]);
                    }
                }
            });

        return $sent;
    }

    // ─── 2. Search alert match ───────────────────────────────────────────────

    public function notifySearchAlertMatches(): int
    {
        $sent = 0;

        $newAds = Ad::query()
            ->where('status', AdStatus::AVAILABLE)
            ->where('updated_at', '>=', now()->subHours(13))
            ->with(['quarter.city'])
            ->get();

        if ($newAds->isEmpty()) {
            return 0;
        }

        SearchAlert::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->has('pushSubscriptions'))
            ->with('user.pushSubscriptions')
            ->chunk(500, function ($alerts) use ($newAds, &$sent): void {
                foreach ($alerts as $alert) {
                    $capKey = "retention_push:search_alert:{$alert->id}";
                    if ($this->isCapped($capKey)) {
                        continue;
                    }

                    /** @var Ad|null $matching */
                    $matching = $newAds->first(fn ($ad) => $alert->matchesAd($ad));
                    if ($matching === null) {
                        continue;
                    }

                    $cityLabel = $matching->quarter?->city?->name ?? $alert->city_name ?? '';
                    $price = $matching->price !== null
                        ? ' — '.number_format((float) $matching->price, 0, ',', ' ').' FCFA'
                        : '';
                    $body = "Nouvelle annonce à {$cityLabel}{$price}.";

                    $result = $this->webPush->sendToUser($alert->user, [
                        'title' => 'Nouvelle annonce pour vous 🔔',
                        'body' => $body,
                        'tag' => "search-alert-{$alert->id}",
                        'url' => "/ads/{$matching->slug}",
                        'actions' => [['action' => 'view', 'title' => "Voir l'annonce"]],
                    ]);

                    if ($result > 0) {
                        $this->setCap($capKey, 86400);
                        $alert->update(['last_notified_at' => now()]);
                        $sent++;
                        Log::info('[RetentionPush] search_alert_match', [
                            'alert_id' => $alert->id,
                            'ad_id' => $matching->id,
                        ]);
                    }
                }
            });

        return $sent;
    }

    // ─── 3. Price drop on favorites ──────────────────────────────────────────

    public function notifyPriceDropOnFavorites(): int
    {
        $sent = 0;
        $minDrop = 5000;

        Ad::query()
            ->where('status', AdStatus::AVAILABLE)
            ->whereNotNull('price')
            ->whereHas('interactions', fn ($q) => $q->where('type', AdInteraction::TYPE_FAVORITE))
            ->chunk(300, function ($ads) use (&$sent, $minDrop): void {
                foreach ($ads as $ad) {
                    $cacheKey = "retention:ad_price:{$ad->id}";
                    $cachedPrice = Cache::get($cacheKey);

                    if ($cachedPrice === null) {
                        Cache::put($cacheKey, (int) $ad->price, now()->addDays(90));

                        continue;
                    }

                    $drop = (int) $cachedPrice - (int) $ad->price;

                    Cache::put($cacheKey, (int) $ad->price, now()->addDays(90));

                    if ($drop < $minDrop) {
                        continue;
                    }

                    $userIds = $this->getNetFavoritingUserIds($ad->id);

                    User::query()
                        ->whereIn('id', $userIds)
                        ->has('pushSubscriptions')
                        ->with('pushSubscriptions')
                        ->each(function ($user) use ($ad, $drop, &$sent): void {
                            $capKey = "retention_push:price_drop:{$user->id}:{$ad->id}";
                            if ($this->isCapped($capKey)) {
                                return;
                            }

                            $result = $this->webPush->sendToUser($user, [
                                'title' => 'Baisse de prix 📉',
                                'body' => "« {$ad->title} » a baissé de ".number_format($drop, 0, ',', ' ').' FCFA !',
                                'tag' => "price-drop-{$ad->id}",
                                'url' => "/ads/{$ad->slug}",
                                'actions' => [['action' => 'view', 'title' => "Voir l'annonce"]],
                            ]);

                            if ($result > 0) {
                                $this->setCap($capKey, 48 * 3600);
                                $sent++;
                                Log::info('[RetentionPush] price_drop', [
                                    'ad_id' => $ad->id,
                                    'user_id' => $user->id,
                                    'drop' => $drop,
                                ]);
                            }
                        });
                }
            });

        return $sent;
    }

    // ─── 4. Viewing reminder ─────────────────────────────────────────────────

    public function notifyViewingReminders(): int
    {
        $sent = 0;
        $tomorrow = now()->addDay()->toDateString();

        TentativeReservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->where('slot_date', $tomorrow)
            ->whereHas('client', fn ($q) => $q->has('pushSubscriptions'))
            ->with(['client.pushSubscriptions', 'ad'])
            ->each(function ($reservation) use (&$sent): void {
                $capKey = "retention_push:viewing_reminder:{$reservation->id}";
                if ($this->isCapped($capKey)) {
                    return;
                }

                $time = mb_substr((string) $reservation->slot_starts_at, 0, 5);
                $title = $reservation->ad?->title ?? 'votre visite';

                $result = $this->webPush->sendToUser($reservation->client, [
                    'title' => 'Rappel de visite ',
                    'body' => "Votre visite de « {$title} » est prévue demain à {$time}.",
                    'tag' => "viewing-{$reservation->id}",
                    'url' => '/owner/reservations',
                    'actions' => [['action' => 'view', 'title' => 'Voir les détails']],
                ]);

                if ($result > 0) {
                    $this->setCap($capKey, 8 * 86400);
                    $sent++;
                    Log::info('[RetentionPush] viewing_reminder', ['reservation_id' => $reservation->id]);
                }
            });

        return $sent;
    }

    // ─── 5. Lease expiry ─────────────────────────────────────────────────────

    public function notifyLeaseExpiries(): int
    {
        $sent = 0;

        foreach ([30, 7] as $daysLeft) {
            $targetDate = now()->addDays($daysLeft)->toDateString();

            LeaseContract::query()
                ->whereDate('lease_end', $targetDate)
                ->whereHas('user', fn ($q) => $q->has('pushSubscriptions'))
                ->with('user.pushSubscriptions')
                ->each(function ($lease) use ($daysLeft, &$sent): void {
                    $capKey = "retention_push:lease_expiry:{$lease->user_id}:{$lease->id}:{$daysLeft}";
                    if ($this->isCapped($capKey)) {
                        return;
                    }

                    $result = $this->webPush->sendToUser($lease->user, [
                        'title' => 'Bail arrivant à expiration 📋',
                        'body' => "Le bail de {$lease->tenant_name} expire dans {$daysLeft} jours. Anticipez le renouvellement.",
                        'tag' => "lease-expiry-{$lease->id}-{$daysLeft}",
                        'url' => '/owner/leases',
                    ]);

                    if ($result > 0) {
                        $this->setCap($capKey, 8 * 86400);
                        $sent++;
                        Log::info('[RetentionPush] lease_expiry', [
                            'lease_id' => $lease->id,
                            'days_left' => $daysLeft,
                        ]);
                    }
                });
        }

        return $sent;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function isCapped(string $key): bool
    {
        return Cache::has($key);
    }

    private function setCap(string $key, int $ttlSeconds): void
    {
        Cache::put($key, 1, $ttlSeconds);
    }

    private function getNetFavoriteCount(string $userId): int
    {
        $favs = AdInteraction::where('user_id', $userId)->where('type', AdInteraction::TYPE_FAVORITE)->count();
        $unfavs = AdInteraction::where('user_id', $userId)->where('type', AdInteraction::TYPE_UNFAVORITE)->count();

        return max(0, $favs - $unfavs);
    }

    /**
     * Returns IDs of users who have net-favorited the given ad
     * (i.e. most recent interaction was TYPE_FAVORITE, not TYPE_UNFAVORITE).
     *
     * @return Collection<int, string>
     */
    private function getNetFavoritingUserIds(string $adId): Collection
    {
        $favorited = AdInteraction::where('ad_id', $adId)->where('type', AdInteraction::TYPE_FAVORITE)->pluck('user_id')->unique();
        $unfavorited = AdInteraction::where('ad_id', $adId)->where('type', AdInteraction::TYPE_UNFAVORITE)->pluck('user_id')->unique();

        return $favorited->diff($unfavorited)->values();
    }
}
