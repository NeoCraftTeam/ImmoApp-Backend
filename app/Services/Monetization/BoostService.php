<?php

declare(strict_types=1);

namespace App\Services\Monetization;

use App\Enums\AdBoostStatus;
use App\Enums\PointTransactionType;
use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\BoostPack;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class BoostService
{
    public function __construct(private PointService $points) {}

    /**
     * Apply a boost pack to an ad, deducting credits atomically.
     *
     * SEC: Caller must already verify ownership (ad.user_id === user.id).
     *
     * @throws \RuntimeException on insufficient balance or already active boost
     */
    public function apply(User $user, Ad $ad, BoostPack $pack): AdBoost
    {
        return DB::transaction(function () use ($user, $ad, $pack): AdBoost {
            if ($ad->isBoosted()) {
                throw new \RuntimeException('Cette annonce est déjà boostée. Attendez l\'expiration avant de booster à nouveau.');
            }

            $this->points->deduct(
                user: $user,
                cost: $pack->price_credits,
                description: "Boost annonce « {$ad->title} » — {$pack->name} ({$pack->duration_days} jours)",
                adId: $ad->id,
                type: PointTransactionType::BOOST,
            );

            $now = now();
            $expiresAt = $now->copy()->addDays($pack->duration_days);

            $boost = AdBoost::create([
                'ad_id' => $ad->id,
                'user_id' => $user->id,
                'boost_pack_id' => $pack->id,
                'credits_spent' => $pack->price_credits,
                'boost_score' => $pack->boost_score,
                'duration_days' => $pack->duration_days,
                'started_at' => $now,
                'expires_at' => $expiresAt,
                'status' => AdBoostStatus::Active,
            ]);

            $ad->boost($pack->boost_score, $pack->duration_days);

            Cache::forget("boost:status:{$ad->id}");

            return $boost;
        });
    }

    /**
     * Expire all ad_boosts whose expires_at <= now, and unboost the ads.
     * Called by the scheduled job ExpireAdBoostsJob.
     *
     * @return int number of boosts expired
     */
    public function expireStale(): int
    {
        $expired = AdBoost::query()
            ->where('status', AdBoostStatus::Active)
            ->where('expires_at', '<=', now())
            ->with('ad')
            ->get();

        foreach ($expired as $boost) {
            DB::transaction(function () use ($boost): void {
                $boost->update(['status' => AdBoostStatus::Expired]);

                if ($boost->ad) {
                    $boost->ad->unboost();
                    Cache::forget("boost:status:{$boost->ad_id}");
                }
            });
        }

        return $expired->count();
    }
}
